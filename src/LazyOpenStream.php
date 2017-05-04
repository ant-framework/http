<?php
namespace Ant\Http;

use Psr\Http\Message\StreamInterface;

/**
 * Class LazyOpenStream
 * @package Ant\Http
 */
class LazyOpenStream implements StreamInterface
{
    protected $filename;

    protected $mode;

    public function __construct($filename, $mode)
    {
        $this->filename = $filename;
        $this->mode = $mode;
    }

    protected function createStream()
    {
        return Stream::tryOpen($this->filename, $this->mode);
    }

    public function __get($name)
    {
        if ($name == 'stream') {
            $this->stream = $this->createStream();
            return $this->stream;
        }

        throw new \UnexpectedValueException("$name not found on class");
    }

    public function __call($method, array $args)
    {
        $result = call_user_func_array([$this->stream, $method], $args);

        return $result === $this->stream ? $this : $result;
    }

    public function __toString()
    {
        return (string) $this->stream;
    }

    public function close()
    {
        $this->stream->close();
    }

    public function detach()
    {
        return $this->stream->detach();
    }

    public function getSize()
    {
        return $this->stream->getSize();
    }

    public function tell()
    {
        return $this->stream->tell();
    }

    public function eof()
    {
        return $this->stream->eof();
    }

    public function isSeekable()
    {
        return $this->stream->isSeekable();
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        $this->stream->seek($offset, $whence);
    }

    public function rewind()
    {
        $this->stream->rewind();
    }

    public function isReadable()
    {
        return $this->stream->isReadable;
    }

    public function read($length)
    {
        return $this->stream->read($length);
    }

    public function isWritable()
    {
        return $this->stream->isWritable();
    }

    public function write($content)
    {
        return $this->stream->write($content);
    }

    public function getContents()
    {
        return $this->stream->getContents();
    }

    public function getMetadata($key = null)
    {
        return $this->stream->getMetadata($key);
    }
}