<?php
namespace Ant\Http\Interfaces;

use Psr\Http\Message\RequestInterface as PsrRequest;

interface RequestInterface extends PsrRequest
{
    /**
     * 通过Http请求的字符流生成Request对象
     *
     * @param $receiveBuffer
     * @return self
     */
    public static function createFromRequestStr($receiveBuffer);

    /**
     * 解析客户端请求的数据格式
     *
     * @return string
     */
    public function getAcceptType();

    /**
     * 获取请求的body类型
     *
     * @return null|string
     */
    public function getContentType();

    /**
     * 获取请求的路由
     *
     * @return string
     */
    public function getRequestRouteUri();

    /**
     * 获取内容长度
     *
     * @return int|null
     */
    public function getContentLength();
}