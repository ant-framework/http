<?php
namespace Ant\Http\Interfaces;

/**
 * Interface RendererInterface
 * @package Ant\Interfaces\Http
 */
interface RendererInterface
{
    /**
     * @param \Psr\Http\Message\MessageInterface $http
     */
    public function __construct(\Psr\Http\Message\MessageInterface $http);

    /**
     * 设置包裹
     *
     * @param $package
     * @return $this
     */
    public function setPackage($package);

    /**
     * 装饰包裹
     *
     * @return \Psr\Http\Message\MessageInterface
     */
    public function decorate();
}