<?php
namespace Ant\Http;

use Psr\Http\Message\StreamInterface;

/**
 * Todo 单元测试
 * Class ServerRequest
 * @package Ant\Http
 * @see http://www.php-fig.org/psr/psr-7/
 */
class CgiServerRequest extends ServerRequest
{
    /**
     * @var string
     */
    protected $routePath;

    /**
     * @var string
     */
    protected $routeSuffix;

    /**
     * @param array $serverParams
     * @param array $cookieParams
     * @param array $queryParams
     * @param array $bodyParams
     * @param array $uploadFiles
     * @param StreamInterface|null $body
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
        parent::__construct($method, $uri, $headers, $body, $protocolVersion, $serverParams);

        // 获取请求资源的路径
        $requestScriptName = $this->getServerParam('SCRIPT_NAME');
        $requestScriptDir = dirname($requestScriptName);
        $routePath = $this->getUri()->getPath();
        $routeSuffix = null;

        // 获取基础路径
        if (stripos($routePath, $requestScriptName) === 0) {
            $basePath = $requestScriptName;
        } elseif ($requestScriptDir !== '/' && stripos($routePath, $requestScriptDir) === 0) {
            $basePath = $requestScriptDir;
        }

        if(isset($basePath)) {
            // 获取请求的路径
            $routePath = '/'.trim(substr($routePath, strlen($basePath)), '/');
        }

        // 取得请求资源的格式(后缀)
        if (false !== ($pos = strrpos($routePath,'.'))) {
            $routeSuffix = substr($routePath, $pos + 1);
            $routePath = strstr($routePath, '.', true);
        }

        $this->routePath = $routePath;
        $this->routeSuffix = $routeSuffix;
    }

    /**
     * @param $key
     * @return array|null
     */
    public function getServerParam($key = null)
    {
        if ($key === null) {
            return $this->serverParams;
        }

        return isset($this->serverParams[$key]) ? $this->serverParams[$key] : null;
    }

    /**
     * @return string
     */
    public function getRoutePath()
    {
        return $this->routePath;
    }

    /**
     * @return string
     */
    public function getRouteSuffix()
    {
        return $this->routeSuffix;
    }
}