<?php
namespace Ant\Http\Exception;

use RuntimeException;

/**
 * Http Exception
 *
 * Class Exception
 * @package Ant\Http
 */
class HttpException extends RuntimeException
{
    protected $statusCode;
    protected $headers;

    /**
     * @param string $statusCode
     * @param null $message
     * @param int $code
     * @param array $headers
     * @param \Exception|null $previous
     */
    public function __construct($statusCode, $message = null, $code = 0, array $headers = array(), \Exception $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取Http状态码
     *
     * @return int|string
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * 获取Http头信息
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param $statusCode
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @param $name
     * @param $value
     */
    public function addHeader($name,$value)
    {
        $this->headers[$name] = $value;
    }
}