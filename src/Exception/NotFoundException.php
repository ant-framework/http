<?php
namespace Ant\Http\Exception;

/**
 * 404异常，当请求的资源不存在时抛出
 *
 * Class NotFoundException
 * @package Ant\Http\Exception
 */
class NotFoundException extends HttpException
{
    public function __construct($message = null, $code = 0, array $headers = [], \Exception $previous = null)
    {
        parent::__construct(404, $message, $code, $headers, $previous);
    }
}