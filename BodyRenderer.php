<?php
namespace Ant\Http;

use Ant\Http\Decorator\Renderer;
use Psr\Http\Message\StreamInterface;
use Ant\Http\Decorator\RendererFactory;

/**
 * Todo 将BodyRenderer移植为中间件
 *
 * Class BodyRenderer
 * @package Ant\Http
 */
trait BodyRenderer
{
    /**
     * body内容为了防止格式错误,只允许设置一次body内容
     *
     * @var bool
     */
    protected $filling = false;

    /**
     * @var null|Renderer
     */
    protected $renderer = null;

    /**
     * @var mixed
     */
    protected $data = null;

    public function withBody(StreamInterface $body)
    {
        $this->filling = false;

        return parent::withBody($body);
    }

    /**
     * 设置响应格式
     *
     * @param $type
     * @return $this
     */
    public function setType($type)
    {
        $this->renderer = RendererFactory::create($type);

        return $this;
    }

    /**
     * 设置响应数据
     *
     * @param $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->checkFilling();
        $this->data = $content;

        return $this;
    }

    /**
     * 用指定格式渲染器进行渲染
     *
     * @return \Psr\Http\Message\MessageInterface
     */
    public function decorate()
    {
        $this->checkFilling();

        if (!$this->renderer instanceof Renderer) {
            throw new \UnexpectedValueException('Please select the appropriate renderer');
        }

        $this->filling = true;

        return $this->renderer
            ->setPackage($this->data)
            ->decorate($this);
    }

    protected function checkFilling()
    {
        // 已被写入body
        if ($this->filling) {
            throw new \RuntimeException('Body has been written');
        }
    }
}