<?php
namespace Ant\Http\Decorator;

use Psr\Http\Message\MessageInterface as PsrMessage;

abstract class Renderer
{
    /**
     * 响应类型
     *
     * @var string
     */
    public $type = 'text/html';

    /**
     * 响应编码
     *
     * @var string
     */
    public $charset = 'utf-8';

    /**
     * 待装饰的包裹
     *
     * @var mixed
     */
    protected $package;

    /**
     * 设置包裹
     *
     * @param $package
     * @return $this
     */
    public function setPackage($package)
    {
        $this->package = $package;

        return $this;
    }

    /**
     * 装饰包裹
     *
     * @return PsrMessage
     */
    abstract public function decorate(PsrMessage $http);

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type.'; charset='.$this->charset;
    }
}