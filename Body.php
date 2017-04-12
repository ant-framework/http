<?php
namespace Ant\Http;

/**
 * Class Body
 * @package Ant\Http
 */
class Body extends Stream
{
    public function __construct($stream = null)
    {
        if (is_null($stream)) {
            $stream = fopen('php://temp','w+');
        }

        parent::__construct($stream);
    }


    /**
     * 通过字符串创建一个流
     *
     * @param string $resource
     * @return static
     */
    public static function createFrom($resource)
    {
        if (!is_scalar($resource)) {
            throw new \InvalidArgumentException("Parameter must be a string");
        }

        $stream = fopen('php://temp', 'r+');

        if ($resource !== '') {
            fwrite($stream, $resource);
            fseek($stream, 0);
        }

        return new static($stream);
    }
}