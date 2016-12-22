<?php
namespace Ant\Http\Exception;

/**
 * 406异常,当无法响应客户端请求的内容格式时抛出
 *
 * Class NotAcceptableException
 * @package Ant\Http\Exception
 */
class NotAcceptableException extends HttpException
{
    public function __construct($message = null, $code = 0, array $headers = [], \Exception $previous = null)
    {
        parent::__construct(406, $message, $code, $headers, $previous);
    }
}