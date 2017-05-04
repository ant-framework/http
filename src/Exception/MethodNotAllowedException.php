<?php
namespace Ant\Http\Exception;

/**
 * 405异常,请求方式错误时抛出
 *
 * Class MethodNotAllowedException
 * @package Ant\Http\Exception
 */
class MethodNotAllowedException extends HttpException
{
    protected $allowed = [];

    public function __construct(
        array $allowed,
        $message = null,
        $code = 0,
        array $headers = [],
        \Exception $previous = null
    ){
        $this->allowed = $allowed;
        $allowed = ['allowed' => implode(',',$allowed)];
        $headers = array_merge($headers,$allowed);

        parent::__construct(405, $message, $code, $headers, $previous);
    }

    /**
     *  获取运行的方法
     *
     * @return array
     */
    public function getAllowedMethod()
    {
        return $this->allowed;
    }
}