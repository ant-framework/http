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
     * 属性
     *
     * @var array
     */
    protected $attributes = [];

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
     * 在“$_SERVER”中不是以“HTTP_”开头的Http头
     *
     * @var array
     */
    protected static $special = [
        'CONTENT_TYPE' => 1,
        'CONTENT_LENGTH' => 1,
        'PHP_AUTH_USER' => 1,
        'PHP_AUTH_PW' => 1,
        'PHP_AUTH_DIGEST' => 1,
        'AUTH_TYPE' => 1,
    ];

    /**
     * @param array $serverParams
     * @param array $cookieParams
     * @param array $queryParams
     * @param array $bodyParams
     * @param array $uploadFiles
     * @param null $body
     * @return ServerRequest
     */
    public static function createFromCgi(
        array $serverParams = [],
        array $cookieParams = [],
        array $queryParams = [],
        array $bodyParams = [],
        array $uploadFiles = [],
        StreamInterface $body = null
    ) {
        $serverParams = $serverParams ?: $_SERVER;
        $cookieParams = $cookieParams ?: $_COOKIE;
        $queryParams = $queryParams ?: $_GET;
        $bodyParams = $bodyParams ?: $_POST;
        $uploadFiles = UploadedFile::parseUploadedFiles($uploadFiles ?: $_FILES);

        $method = isset($serverParams['REQUEST_METHOD']) ? $serverParams['REQUEST_METHOD'] : 'GET';

        $headers = function_exists('getallheaders')
            ? getallheaders()
            : static::parserServerHeader($serverParams);

        $uri = Uri::createFromServerParams($serverParams);

        $body = $body ?: RequestBody::createFromCgi();

        $protocol = isset($serverParams['SERVER_PROTOCOL'])
            ? str_replace('HTTP/', '', $serverParams['SERVER_PROTOCOL'])
            : '1.1';

        $serverRequest = new ServerRequest($method, $uri, $headers, $body, $protocol, $serverParams);

        return $serverRequest
            ->withCookieParams($cookieParams)
            ->withQueryParams($queryParams)
            ->withParsedBody($bodyParams)
            ->withUploadedFiles($uploadFiles);
    }

    /**
     * 从Server参数中提取Http Header
     *
     * @param $serverParams
     * @return array
     */
    protected static function parserServerHeader($serverParams)
    {
        $headers = [];
        foreach ($serverParams as $key => $value) {
            //提取HTTP头
            if (isset(static::$special[$key]) || strpos($key, 'HTTP_') === 0) {
                $key = strtolower(str_replace('_', '-', $key));
                $key = (strpos($key, 'http-') === 0) ? substr($key, 5) : $key;
                $headers[$key] = explode(',', $value);
            }
        }

        return $headers;
    }

    /**
     * 通过Tcp输入流解析Http请求
     *
     * @param $receiveBuffer
     * @param array $serverParams
     * @return ServerRequest
     */
    final public static function createFromString($receiveBuffer, array $serverParams = [])
    {
        list($startLine, $headers, $body) = static::parseMessage($receiveBuffer);
        // 解析起始行
        list($method, $requestTarget, $protocol) = explode(' ', $startLine, 3);
        // 获取Http协议版本
        $protocolVersion = str_replace('HTTP/', '', $protocol);
        // 获取Uri
        $uri = static::createUri($requestTarget, $headers);

        $request = new ServerRequest($method, $uri, $headers, $body, $protocolVersion, $serverParams);
        // 注册Body基础解析器
        $request->registerBaseBodyParsers();

        return $request;
    }

    /**
     * @param $path
     * @param array $headers
     * @return string
     */
    protected static function createUri($path, array $headers = [])
    {
        $hostKey = array_filter(array_keys($headers), function ($k) {
            return strtolower($k) === 'host';
        });

        if (!$hostKey) {
            return $path;
        }

        $host = $headers[reset($hostKey)][0];
        $scheme = substr($host, -4) === ':443' ? 'https' : 'http';

        return $scheme . '://' . $host . '/' . ltrim($path, '/');
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
     * @return ServerRequest
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
     * @return ServerRequest
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
     * @return ServerRequest
     */
    public function withAddedQueryParams(array $query)
    {
        return $this->withQueryParams(array_merge($this->getQueryParams(), $query));
    }

    /**
     * 添加body数据
     *
     * @param StreamInterface $body
     * @return ServerRequest
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
        if (!$this->usesBody) {
            $this->parseFromData($this->getBody()->__toString());
        }

        return $this->uploadFiles;
    }

    /**
     * 添加上传文件信息
     *
     * @param array $uploadedFiles
     * @return ServerRequest
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
                $parsed = call_user_func(
                    $this->bodyParsers[$contentType],
                    $this->getBody()->__toString()
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
     * @return ServerRequest
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
     * @return ServerRequest
     */
    public function withAttribute($name, $value)
    {
        return $this->changeAttribute(['attributes',$name], $value);
    }

    /**
     * 删除一个属性
     *
     * @see getAttributes()
     * @param string $name .
     * @return ServerRequest
     */
    public function withoutAttribute($name)
    {
        $self = clone $this;
        if (array_key_exists($name, $self->attributes)) {
            unset($self->attributes[$name]);
        }

        return $self;
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
        $this->bodyParsers = [
            // 解析Xml数据,
            'text/xml'                          =>  [BodyParsers::class, "parseXml"],
            'application/xml'                   =>  [BodyParsers::class, "parseXml"],
            // 解析Json数据
            'text/json'                         =>  [BodyParsers::class, "parseJson"],
            'application/json'                  =>  [BodyParsers::class, "parseJson"],
            // 解析表单数据
            'multipart/form-data'               =>  [$this, "parseFromData"],
            // 解析Url encode格式
            'application/x-www-form-urlencoded' =>  [BodyParsers::class, "parseUrlEncode"],
        ];
    }

    /**
     * @param $input
     * @return array|null
     */
    protected function parseFromData($input)
    {
        if (!preg_match('/boundary="?(\S+)"?/', $this->getHeaderLine('content-type'), $match)) {
            return null;
        }

        $data = [];
        $bodyBoundary = '--' . $match[1] . "\r\n";
        // 将最后一行分界符剔除
        $body = substr(
            $input, 0,
            $this->getBody()->getSize() - (strlen($bodyBoundary) + 4)
        );

        foreach (explode($bodyBoundary, $body) as $buffer) {
            if ($buffer == '') {
                continue;
            }

            // 将Body头信息跟内容拆分
            list($header, $bufferBody) = explode("\r\n\r\n", $buffer, 2);
            $bufferBody = substr($bufferBody, 0, -2);
            $disposition = $this->getContentDisposition($header);

            if (!$disposition) {
                continue;
            }

            if (preg_match('/name="(.*?)"; filename="(.*?)"$/', $disposition, $match)) {
                $file = new Stream(fopen('php://temp','w'));
                $file->write($bufferBody);
                $file->rewind();

                $this->uploadFiles[$match[1]] = new UploadedFile([
                    'stream'    => $file,
                    'name'      => $match[1],
                    'size'      => $file->getSize()
                ]);
            } elseif (preg_match('/name="(.*?)"$/', $disposition, $match)) {
                $data[$match[1]] = $bufferBody;
            }
        }

        return $data;
    }

    /**
     * @param $header
     * @return bool
     */
    protected function getContentDisposition($header)
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