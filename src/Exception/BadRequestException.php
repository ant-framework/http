<?php
namespace Ant\Http\Exception;

/**
 * 400异常,当客户端请求的参数不正确时抛出
 *
 * Class BadRequestException
 * @package Ant\Http\Exception
 */
class BadRequestException extends HttpException
{
    public function __construct($message = null, $code = 0, array $headers = [], \Exception $previous = null)
    {
        parent::__construct(400, $message, $code, $headers, $previous);
    }
}