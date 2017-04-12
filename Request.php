<?php
namespace Ant\Http;

use RuntimeException;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Todo 将现有ServerRequest更改为CgiRequest
 * Todo 重构ServerRequest
 *
 * Class Request
 * @package Ant\Http
 * @see http://www.php-fig.org/psr/psr-7/
 */
class Request extends Message implements RequestInterface
{
    /**
     * http 请求方式
     *
     * @var string
     */
    protected $method = null;

    /**
     * Uri 实例
     *
     * @var \Psr\Http\Message\UriInterface
     */
    protected $uri = null;

    /**
     * cookie参数
     *
     * @var array
     */
    protected $cookieParams = [];

    /**
     * 查询参数
     *
     * @var array
     */
    protected $queryParams = [];

    /**
     * http上传文件 \Psr\Http\Message\UploadedFileInterface 实例
     *
     * @var array
     */
    protected $uploadFiles = [];

    /**
     * body 参数
     *
     * @var array|object|null
     */
    protected $bodyParams = null;

    /**
     * body 解析器 根据subtype进行调用
     *
     * @var array
     */
    protected $bodyParsers = [];

    /**
     * body是否使用过
     *
     * @var bool
     */
    protected $usesBody = false;

    /**
     * 通过Tcp输入流解析Http请求
     *
     * @param string $receiveBuffer
     * @return static
     */
    final public static function createFromRequestStr($receiveBuffer)
    {
        if (!is_string($receiveBuffer)) {
            throw new \InvalidArgumentException('Request must be string');
        }

        list($startLine, $headers, $bodyBuffer) = static::parseMessage($receiveBuffer);

        // 解析起始行
        list($method, $requestTarget, $protocol) = explode(' ', $startLine, 3);
        $protocolVersion = str_replace('HTTP/', '', $protocol);

        // 获取Uri
        $uri = (isset($headers['host']) ? 'http://'.$headers['host'][0] : '') . $requestTarget;

        // 将Body写入流
        $body = ($method == 'GET')
            ? new RequestBody(fopen('php://temp','r+'))
            : RequestBody::createFrom($bodyBuffer);

        $request = new Request($method, $uri, $headers, $body, $protocolVersion);
        // 注册Body基础解析器
        $request->registerBaseBodyParsers();

        return $request;
    }

    /**
     * Request constructor.
     * @param string $method                    Http动词
     * @param string $uri                       请求的Uri
     * @param array $headers                    Http头
     * @param mixed $body        Body内容
     * @param string $protocolVersion           Http协议版本
     */
    public function __construct(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $protocolVersion = '1.1'
    ) {
        if (!$uri instanceof UriInterface) {
            $uri = new Uri($uri);
        }

        $this->method = $method;
        $this->uri = $uri;
        $this->headers = $headers;
        $this->body = $body ?: new Body();
        $this->protocolVersion = $protocolVersion;

        // 如果没设置Host,尝试通过Uri获取
        if (!$this->hasHeader("host")) {
            $this->updateHostFromUri();
        }

        //解析GET与Cookie参数
        parse_str($this->uri->getQuery(), $this->queryParams);
        parse_str(str_replace([';','; '], '&', $this->getHeaderLine('Cookie')), $this->cookieParams);
    }

    /**
     * Todo::将文件写入Body
     * 以字符串的形式输出请求
     *
     * @return string
     */
    public function __toString()
    {
        if ($cookie = $this->getCookieParams()) {
            //设置Cookie
            $this->headers['cookie'] = str_replace('&','; ',http_build_query($this->getCookieParams()));
        }

        if ($size = $this->getBody()->getSize()) {
            //设置Body长度
            $this->headers['content-length'] = [$size];
        }

        $requestString = sprintf(
            "%s %s HTTP/%s\r\n",
            $this->getOriginalMethod(),
            $this->getRequestTarget(),
            $this->getProtocolVersion()
        );

        $requestString .= $this->headerToString();
        $requestString .= PHP_EOL;
        $requestString .= (string)$this->getBody();

        return $requestString;
    }

    /**
     * 获取重写后的http请求方式
     *
     * @return string
     */
    public function getMethod()
    {
        $method = $this->method;

        //检查是否在报头中重载了http动词
        if ($customMethod = $this->getHeaderLine('x-http-method-override')) {
            $method = $customMethod;
        }

        //当请求方式为Post时,检查是否为表单提交,跟请求重写
        if ($this->method == 'POST' && $customMethod = $this->getBodyParam('_method')) {
            $method = $customMethod;
        }

        return strtoupper($method);
    }

    /**
     * 获取原始的http请求方式
     *
     * @return string
     */
    public function getOriginalMethod()
    {
        return $this->method;
    }

    /**
     * 设置请求方式
     *
     * @param string $method
     * @return Request
     */
    public function withMethod($method)
    {
        return $this->changeAttribute('method', $method);
    }

    /**
     * 获取请求目标(资源)
     *
     * @return string
     */
    public function getRequestTarget()
    {
        $requestTarget = $this->uri->getPath();

        if ($query = $this->uri->getQuery()) {
            $requestTarget .= '?'.$query;
        }

        if ($fragment = $this->uri->getFragment()) {
            $requestTarget .= '#'.$fragment;
        }

        return $requestTarget;
    }

    /**
     * 设置请求资源
     *
     * @param mixed $requestTarget
     * @return Request
     */
    public function withRequestTarget($requestTarget)
    {
        if (!is_string($requestTarget)) {
            throw new InvalidArgumentException('The request target must be a string');
        }

        $meta = parse_url($requestTarget);

        $path = isset($meta['path']) ? $meta['path'] : '';
        $query = isset($meta['query']) ? $meta['query'] : '';
        $fragment = isset($meta['fragment']) ? $meta['fragment'] : '';

        parse_str($query, $this->queryParams);

        $uri = $this->uri
            ->withPath($path)
            ->withQuery($query)
            ->withFragment($fragment);

        // 启用Host保护,修改请求目标,不应该影响Host
        return $this->withUri($uri, true);
    }

    /**
     * 获取URI
     *
     * @return UriInterface
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * 设置uri
     *
     * @param UriInterface $uri
     * @param bool|false $preserveHost
     * @return Request
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $self = $this->changeAttribute('uri', $uri);
        parse_str($uri->getQuery(), $self->queryParams);

        // 如果开启host保护,原Host为空且新Uri包含Host时才更新
        if (!$preserveHost) {
            $host = explode(',', $uri->getHost());
        } elseif ((!$this->hasHeader('host') || empty($this->getHeaderLine('host'))) && $uri->getHost() !== '') {
            $host = explode(',', $uri->getHost());
        }

        if (isset($host)) {
            $self->updateHostFromUri();
        }

        return $self;
    }

    /**
     * 获取查询参数
     *
     * @return array
     */
    public function getQueryParams()
    {
        return $this->queryParams;
    }

    /**
     * 设置查询参数
     *
     * @param array $query
     * @return Request
     */
    public function withQueryParams(array $query)
    {
        $result = $this->changeAttribute('queryParams', $query);
        //修改查询参数
        $result->uri = $result->uri->withQuery($query);

        return $result;
    }

    /**
     * 向get中添加参数
     *
     * @param array $query
     * @return Request
     */
    public function withAddedQueryParams(array $query)
    {
        return $this->withQueryParams(array_merge($this->getQueryParams(), $query));
    }

    /**
     * 获取cookie参数
     *
     * @return array
     */
    public function getCookieParams()
    {
        return $this->cookieParams;
    }

    /**
     * 设置cookie参数
     *
     * @param array $cookies
     * @return Request
     */
    public function withCookieParams(array $cookies)
    {
        return $this->changeAttribute('cookieParams', $cookies);
    }

    /**
     * 添加body数据
     *
     * @param StreamInterface $body
     * @return $this|Message
     */
    public function withBody(StreamInterface $body)
    {
        //当Body被修改后,允许重新解析body
        $this->usesBody = false;

        return parent::withBody($body);
    }

    /**
     * 获取上传文件信息
     *
     * @return array
     */
    public function getUploadedFiles()
    {
        return $this->uploadFiles;
    }

    /**
     * 添加上传文件信息
     *
     * @param array $uploadedFiles
     * @return Request
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        return $this->changeAttribute('uploadFiles', $uploadedFiles);
    }

    /**
     * 获取body解析结果
     *
     * @return array|null|object
     */
    public function getParsedBody()
    {
        // 解析成功直接返回解析结果,如果解析后的参数为空,不允许进行第二次解析
        if (!empty($this->bodyParams) || $this->usesBody) {
            return $this->bodyParams;
        }

        $this->usesBody = true;

        if ($contentType = $this->getContentType()) {
            // 用自定义方法解析Body内容
            if ($this->body->getSize() !== 0 && isset($this->bodyParsers[$contentType])) {
                // 调用body解析函数
                $parsed = call_user_func_array(
                    $this->bodyParsers[$contentType],
                    [$this->getBody()->__toString(), $this]
                );

                if (!(is_null($parsed) || is_object($parsed) || is_array($parsed))) {
                    throw new RuntimeException(
                        'Request body media type parser return value must be an array, an object, or null'
                    );
                }

                return $this->bodyParams = $parsed;
            }
        }

        return null;
    }

    /**
     * 设置body解析结果
     *
     * @param array|null|object $data
     * @return Request
     */
    public function withParsedBody($data)
    {
        if (!(is_null($data) || is_array($data) || is_object($data))) {
            throw new InvalidArgumentException('Parsed body value must be an array, an object, or null');
        }

        return $this->changeAttribute('bodyParams', $data);
    }

    /**
     * 获取body参数
     *
     * @param null $key
     * @return array|null|object
     */
    public function getBodyParam($key = null)
    {
        if (is_null($key)) {
            return $this->getParsedBody();
        }

        $params = $this->getParsedBody();

        if (is_array($params) && array_key_exists($key,$params)) {
            return $params[$key];
        } elseif (is_object($params) && property_exists($params, $key)) {
            return $params->$key;
        }

        return null;
    }

    /**
     * 获取请求的body类型
     *
     * @return null|string
     */
    public function getContentType()
    {
        $result = $this->getHeader('Content-Type');
        $contentType = $result ? $result[0] : null;

        if ($contentType) {
            $contentTypeParts = preg_split('/\s*[;,]\s*/', $contentType);

            $contentType = strtolower($contentTypeParts[0]);
        }

        return $contentType;
    }

    /**
     * 获取内容长度
     *
     * @return int|null
     */
    public function getContentLength()
    {
        $result = $this->getHeader('Content-Length');

        return $result ? (int)$result[0] : null;
    }

    /**
     * 设置body解析器
     *
     * @param $subtype string
     * @param $parsers callable
     */
    public function setBodyParsers($subtype, $parsers)
    {
        if (!is_callable($parsers)) {
            throw new InvalidArgumentException('Body parsers must be a callable');
        }

        $this->usesBody = false;
        $this->bodyParsers[$subtype] = $parsers;
    }

    /**
     * 注册默认body解析器
     */
    public function registerBaseBodyParsers()
    {
        $this->bodyParsers = BodyParsers::create($this);
    }

    /**
     * 检查请求方式
     *
     * @param $method
     * @return bool
     */
    public function isMethod($method)
    {
        return $this->getMethod() === $method;
    }

    /**
     * 查看是否是GET请求
     *
     * @return bool
     */
    public function isGet()
    {
        return $this->isMethod('GET');
    }

    /**
     * 查看是否是POST请求
     *
     * @return bool
     */
    public function isPost()
    {
        return $this->isMethod('POST');
    }

    /**
     * 查看是否是PUT请求
     *
     * @return bool
     */
    public function isPut()
    {
        return $this->isMethod('PUT');
    }

    /**
     * 查看是否是DELETE请求
     *
     * @return bool
     */
    public function isDelete()
    {
        return $this->isMethod('DELETE');
    }

    /**
     * 检查是否是异步请求
     * 注意 : 主流JS框架发起AJAX都有此参数,如果是原生AJAX需要手动添加到http头
     *
     * @return bool
     */
    public function isAjax()
    {
        return strtolower($this->getHeaderLine('x-requested-with')) === 'xmlhttprequest';
    }

    /**
     * 通过Uri更新请求主机名
     */
    protected function updateHostFromUri()
    {
        $host = $this->uri->getHost();

        if ($host == '') {
            return;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        $this->headers['host'] = [$host];
    }
}