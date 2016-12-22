<?php
namespace Ant\Http\Exception;

/**
 * 401异常,客户端权限不足时抛出
 *
 * Class UnauthorizedException
 * @package Ant\Http\Exception
 */
class UnauthorizedException extends HttpException
{
    public function __construct($message = null, $code = 0, array $headers = [], \Exception $previous = null)
    {
        parent::__construct(401, $message, $code, $headers, $previous);
    }
}