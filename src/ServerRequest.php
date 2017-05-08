<?php
namespace Ant\Http;

use RuntimeException;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ServerRequest
 * @package Ant\Http
 * @see http://www.php-fig.org/psr/psr-7/
 */
class ServerRequest extends Request implements ServerRequestInterface
{
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
     * body 参数
     *
     * @var array|object|null
     */
    protected $bodyParams = null;

    /**
     * http上传文件 \Psr\Http\Message\UploadedFileInterface 实例
     *
     * @var array
     */
    protected $uploadFiles = [];

    /**
     * Server参数
     *
     * @var array
     */
    protected $serverParams = [];

    /**
     * 属性
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * 是否允许解析Body
     *
     * @var bool
     */
    protected $allowParseBody = true;

    /**
     * body 解析器 根据subtype进行调用
     *
     * @var array
     */
    protected $bodyParsers = [
        // 解析Xml数据,
        'text/xml'                          =>  [ServerRequest::class, "parseXml"],
        'application/xml'                   =>  [ServerRequest::class, "parseXml"],
        // 解析Json数据
        'text/json'                         =>  [ServerRequest::class, "parseJson"],
        'application/json'                  =>  [ServerRequest::class, "parseJson"],
        // 解析表单数据
        'multipart/form-data'               =>  [ServerRequest::class, "parseFromData"],
        // 解析Url encode格式
        'application/x-www-form-urlencoded' =>  [ServerRequest::class, "parseUrlEncode"],
    ];

    /**
     * @param string $method
     * @param string $uri
     * @param array $headers
     * @param null $body
     * @param string $protocolVersion
     * @param array $serverParams
     */
    public function __construct(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $protocolVersion = '1.1',
        array $serverParams = []
    ) {
        $this->serverParams = $serverParams;

        parent::__construct($method, $uri, $headers, $body, $protocolVersion);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($cookie = $this->getCookieParams()) {
            // 设置Cookie
            $this->headers['cookie'] = [str_replace('&', '; ', http_build_query($this->getCookieParams()))];
        }

        return parent::__toString();
    }

    /**
     * 获取重写后的http请求方式
     *
     * @return string
     */
    public function getMethod()
    {
        $method = $this->method;

        // 检查是否在报头中重载了http动词
        if ($customMethod = $this->getHeaderLine('x-http-method-override')) {
            $method = $customMethod;
        }

        // 当请求方式为Post时,检查是否为表单提交,跟请求重写
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
     * 设置uri
     *
     * @param UriInterface $uri
     * @param bool|false $preserveHost
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $self = parent::withUri($uri, $preserveHost);
        parse_str($uri->getQuery(), $self->queryParams);

        return $self;
    }

    /**
     * @return array
     */
    public function getServerParams()
    {
        return $this->serverParams;
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
     * @return self
     */
    public function withCookieParams(array $cookies)
    {
        return $this->changeAttribute('cookieParams', $cookies);
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
     * @return self
     */
    public function withQueryParams(array $query)
    {
        $result = $this->changeAttribute('queryParams', $query);
        // 修改查询参数
        $result->uri = $result->uri->withQuery($query);

        return $result;
    }

    /**
     * 向get中添加参数
     *
     * @param array $query
     * @return self
     */
    public function withAddedQueryParams(array $query)
    {
        return $this->withQueryParams(array_merge($this->getQueryParams(), $query));
    }

    /**
     * 添加body数据
     *
     * @param StreamInterface $body
     * @return self
     */
    public function withBody(StreamInterface $body)
    {
        // 当Body被修改后,允许重新解析body
        $this->allowParseBody = true;

        return parent::withBody($body);
    }

    /**
     * 获取上传文件信息
     *
     * @return array
     */
    public function getUploadedFiles()
    {
        if (!$this->uploadFiles && $this->allowParseBody) {
            $this->parseBody();
        }

        return $this->uploadFiles;
    }

    /**
     * 添加上传文件信息
     *
     * @param array $uploadedFiles
     * @return self
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
        // 如果已经解析过Body,或者Body参数不为空时返回
        if (!$this->allowParseBody || !empty($this->bodyParams)) {
            return $this->bodyParams;
        }

        $this->parseBody();

        return $this->bodyParams;
    }

    /**
     * 初始化Body参数
     */
    protected function parseBody()
    {
        // 在Body跟解析更新前只允许解析一次Body
        $this->allowParseBody = false;
        $contentType = $this->getContentType();

        // Header有声明Body类型,Body不为空且解析器存在
        if (
            !$contentType ||
            $this->body->getSize() === 0 ||
            empty($this->bodyParsers[$contentType])
        ) {
            return;
        }

        // 调用body解析函数
        $parsed = call_user_func_array(
            $this->bodyParsers[$contentType],
            [$this->getBody()->__toString(), $this]
        );

        // Body解析后类型必须为Null,Object,Array
        if (!is_null($parsed) && !is_object($parsed) && !is_array($parsed)) {
            throw new RuntimeException(
                'Request body media type parser return value must be an array, an object, or null'
            );
        }

        $this->bodyParams = $parsed;
    }

    /**
     * 获取body参数
     *
     * @param null $key
     * @return array|null|object
     */
    public function getBodyParam($key = null)
    {
        $params = $this->getParsedBody();

        if (is_null($key)) {
            return $params;
        }

        if (is_array($params) && array_key_exists($key,$params)) {
            return $params[$key];
        }

        if (is_object($params) && property_exists($params, $key)) {
            return $params->$key;
        }

        return null;
    }

    /**
     * 设置body解析结果
     *
     * @param array|null|object $data
     * @return self
     */
    public function withParsedBody($data)
    {
        if (!(is_null($data) || is_array($data) || is_object($data))) {
            throw new InvalidArgumentException('Parsed body value must be an array, an object, or null');
        }

        return $this->changeAttribute('bodyParams', $data);
    }

    /**
     * 获取所有属性
     *
     * @return mixed[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * 获取一个属性的值
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes)
            ? $this->attributes[$name]
            : $default;
    }

    /**
     * 设置一个属性.
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function withAttribute($name, $value)
    {
        return $this->changeAttribute(['attributes', $name], $value);
    }

    /**
     * 删除一个属性
     *
     * @see getAttributes()
     * @param string $name .
     * @return self
     */
    public function withoutAttribute($name)
    {
        $attr = $this->attributes;
        if (array_key_exists($name, $attr)) {
            unset($attr[$name]);
        }

        return $this->changeAttribute('attributes', $attr);
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

        $this->allowParseBody = true;
        $this->bodyParsers[$subtype] = $parsers;
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
     * @return string
     */
    protected function getStartLine()
    {
        return sprintf(
            "%s %s HTTP/%s\r\n",
            $this->getOriginalMethod(),
            $this->getRequestTarget(),
            $this->getProtocolVersion()
        );
    }

    /**
     * @param string $input
     * @return array|null
     */
    protected static function parseJson($input)
    {
        $data = json_decode($input, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * @param string $input
     * @return \SimpleXMLElement
     */
    protected static function parseXml($input)
    {
        $backup = libxml_disable_entity_loader(true);
        $data = simplexml_load_string($input);
        libxml_disable_entity_loader($backup);
        return $data;
    }

    /**
     * @param string $input
     * @return array|null
     */
    protected static function parseUrlEncode($input)
    {
        parse_str(trim($input), $data);
        return $data;
    }

    /**
     * @param $input
     * @return array|null
     */
    protected static function parseFromData($input, ServerRequest $req)
    {
        if (!preg_match('/boundary="?(\S+)"?/', $req->getHeaderLine('content-type'), $match)) {
            return null;
        }

        $data = [];
        $bodyBoundary = '--' . $match[1] . "\r\n";
        // 将最后一行分界符剔除
        $body = substr(
            $input, 0,
            $req->getBody()->getSize() - (strlen($bodyBoundary) + 4)
        );

        foreach (explode($bodyBoundary, $body) as $buffer) {
            if ($buffer == '') {
                continue;
            }

            // 将Body头信息跟内容拆分
            list($header, $bufferBody) = explode("\r\n\r\n", $buffer, 2);
            $bufferBody = substr($bufferBody, 0, -2);
            $disposition = static::getContentDisposition($header);

            if (!$disposition) {
                continue;
            }

            // 普通数据为文件类型的超集,优先匹配文件类型
            if (preg_match('/name="(.*?)"; filename="(.*?)"$/', $disposition, $match)) {
                list(,$name, $clientName) = $match;
                $stream = new Stream(fopen('php://temp','w'));
                $stream->write($bufferBody);
                $stream->seek(0);

                $req->uploadFiles[$name] = new UploadedFile(
                    $stream,
                    $stream->getSize(),
                    UPLOAD_ERR_OK,
                    $clientName
                );
            } elseif (preg_match('/name="(.*?)"$/', $disposition, $match)) {
                $data[$match[1]] = $bufferBody;
            }
        }

        return $data;
    }

    /**
     * 获取From表单中的字段信息
     *
     * @param $header
     * @return bool
     */
    protected static function getContentDisposition($header)
    {
        foreach (explode("\r\n", $header) as $item) {
            list($headerName, $headerData) = explode(":", $item, 2);
            $headerName = trim(strtolower($headerName));
            if ($headerName == 'content-disposition') {
                return $headerData;
            }
        }

        return false;
    }
}