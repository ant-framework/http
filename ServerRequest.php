<?php
namespace Ant\Http;

use RuntimeException;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ServerRequestInterface;

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

        $request = new ServerRequest($method, $uri, $headers, $body, $protocolVersion);
        // 注册Body基础解析器
        $request->registerBaseBodyParsers();

        return $request;
    }

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

        // 解析Get与Cookie参数
        parse_str($this->uri->getQuery(), $this->queryParams);
        parse_str(str_replace([';','; '], '&', $this->getHeaderLine('Cookie')), $this->cookieParams);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($cookie = $this->getCookieParams()) {
            //设置Cookie
            $this->headers['cookie'] = str_replace('&','; ',http_build_query($this->getCookieParams()));
        }

        return parent::__toString();
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
     * @return Request
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
}