<?php
namespace Ant\Http\Message;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Ant\Http\Interfaces\RendererInterface;

abstract class Renderer implements RendererInterface
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
     * @var MessageInterface
     */
    protected $httpMessage;

    /**
     * @param MessageInterface $http
     */
    public function __construct(MessageInterface $http)
    {
        $this->httpMessage = $http;
    }

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
     * @return string
     */
    public function getType()
    {
        $charset = ($this->httpMessage instanceof ResponseInterface) ? ';charset='.$this->charset : '';
        return $this->type.$charset;
    }
}