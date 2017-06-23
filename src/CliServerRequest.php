<?php
namespace Ant\Http;

/**
 * Class CliServerRequest
 * @package Ant\Http
 */
class CliServerRequest extends ServerRequest
{
    /**
     * 通过Tcp输入流解析Http请求
     *
     * @param $receiveBuffer
     * @param array $serverParams
     * @return ServerRequest
     */
    public static function createFromString($receiveBuffer, array $serverParams = [])
    {
        list($startLine, $headers, $body) = static::parseMessage($receiveBuffer);
        // 解析起始行
        list($method, $requestTarget, $protocol) = explode(' ', $startLine, 3);
        // 获取Http协议版本
        $protocolVersion = str_replace('HTTP/', '', $protocol);
        // 获取Uri
        $uri = static::createUri($requestTarget, $headers);

        return new static($method, $uri, $headers, $body, $protocolVersion, $serverParams);
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
        parent::__construct($method, $uri, $headers, $body, $protocolVersion, $serverParams);
        // 解析Get与Cookie参数
        parse_str($this->uri->getQuery(), $this->queryParams);

        if ($this->hasHeader("Cookie")) {
            parse_str(str_replace([';', '; '], '&', $this->getHeaderLine('Cookie')), $this->cookieParams);
        }
    }
}