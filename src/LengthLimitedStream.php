<?php
namespace Ant\Http;

use Psr\Http\Message\StreamInterface;

/**
 * 大小限制流
 *
 * Class LengthLimitedStream
 * @package Ant\Http
 */
class LengthLimitedStream extends Stream implements StreamInterface
{
    protected $maxLength;

    /**
     * 处理一个stream资源
     *
     * Stream constructor.
     * @param $stream resource 只接受资源类型
     */
    public function __construct($stream, $maxLength)
    {
        parent::__construct($stream);

        $this->maxLength = $maxLength;

        if ($this->getSize() === $this->maxLength) {
            $this->isWritable = false;
        }
    }

    /**
     * @param string $string
     * @return int
     */
    public function write($string)
    {
        if ($this->getSize() + strlen($string) > $this->maxLength) {
            $string = substr($string, 0, $this->maxLength - $this->getSize());
        }

        $written = parent::write($string);

        if ($this->getSize() === $this->maxLength) {
            $this->isWritable = false;
        }

        return $written;
    }
}