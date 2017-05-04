<?php
namespace Ant\Http\Exception;

/**
 * 403错误,理解客户端请求后仍要拒绝请求时抛出
 *
 * Class ForbiddenException
 * @package Ant\Http\Exception
 */
class ForbiddenException extends HttpException
{
    public function __construct($message = null, $code = 0, array $headers = [], \Exception $previous = null)
    {
        parent::__construct(403, $message, $code, $headers, $previous);
    }
}