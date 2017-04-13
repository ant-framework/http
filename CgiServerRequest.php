<?php
namespace Ant\Http;

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