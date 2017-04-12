<?php
namespace Ant\Http;

/**
 * Class RequestBody
 * @package Ant\Http
 */
class RequestBody extends Body
{
    /**
     * 获取请求内容
     */
    public static function createFromCgi()
    {
        // 必须用stream_copy_to_stream将input流拷贝到另一个流上,不然无法使用fstat函数
        $stream = fopen('php://temp','w+');
        stream_copy_to_stream(fopen('php://input','r'),$stream);
        fseek($stream, 0);

        return new static($stream);
    }
}