<?php
namespace Ant\Http;

use RuntimeException;
use UnexpectedValueException;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * Class Stream
 * @package Ant\Http
 * Todo IteratorStream
 *
 * @note
 * stream "php://output"  mode can only be "wb"
 * stream "php://input"   mode can only be "rb"
 * stream "php://memory"  mode certain support "rb"
 * stream "php://temp"    mode certain support "rb"
 */
class Stream implements StreamInterface
{
    /**
     * @var resource
     */
    protected $stream;

    /**
     * @var bool 是否可以定位
     */
    protected $isSeekable = false;

    /**
     * @var bool 是否可读
     */
    protected $isReadable = false;

    /**
     * @var bool 是否可写
     */
    protected $isWritable = false;

    /**
     * @var array 流的元数据
     */
    protected $metadata = [];

    /**
     * 可用读写模式
     *
     * @var array
     */
    protected static $readWriteHash = [
        'read' => [
            'r' => true, 'w+' => true, 'r+' => true, 'x+' => true, 'c+' => true,
            'rb' => true, 'w+b' => true, 'r+b' => true, 'x+b' => true,
            'c+b' => true, 'rt' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a+' => true,
        ],
        'write' => [
            'w' => true, 'w+' => true, 'rw' => true, 'r+' => true, 'x+' => true,
            'c+' => true, 'wb' => true, 'w+b' => true, 'r+b' => true,
            'x+b' => true, 'c+b' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a' => true, 'a+' => true,
        ]
    ];

    /**
     * 通过字符串创建一个流
     *
     * @param string $resource
     * @return static
     */
    public static function createFrom($resource)
    {
        if (is_object($resource) && $resource instanceof StreamInterface) {
            return $resource;
        }

        if (is_scalar($resource) || is_null($resource)) {
            $stream = fopen('php://temp', 'r+');

            if ($resource !== '') {
                fwrite($stream, $resource);
                fseek($stream, 0);
            }

            return new static($stream);
        }

        throw new RuntimeException("Error");
    }

    /**
     * 获取请求内容
     */
    public static function createFromCgi()
    {
        // 必须用stream_copy_to_stream将input流拷贝到另一个流上,不然无法使用fstat函数
        $stream = fopen('php://temp', 'w+');
        stream_copy_to_stream(fopen('php://input', 'r'), $stream);
        fseek($stream, 0);

        return new static($stream);
    }

    /**
     * 处理一个stream资源
     *
     * Stream constructor.
     * @param $stream resource 只接受资源类型
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new UnexpectedValueException(__METHOD__ . ' argument must be a valid PHP resource');
        }

        $this->stream = $stream;
        $meta = stream_get_meta_data($this->stream);

        $this->metadata = $meta;
        $this->isSeekable = $meta['seekable'];
        $this->isReadable = isset(static::$readWriteHash['read'][$meta['mode']]);
        $this->isWritable = isset(static::$readWriteHash['write'][$meta['mode']]);
    }

    /**
     * 在销毁对象时关闭流
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * 从stream读取所有数据到一个字符串
     *
     * @return string
     */
    public function __toString()
    {
        if (!$this->isAttached()) {
            return '';
        }

        try {
            $this->rewind();
            return $this->getContents();
        } catch (RuntimeException $e) {
            //如果不可读返回空字符串
            return '';
        }
    }

    /**
     * 关闭stream
     *
     * @return void
     */
    public function close()
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    /**
     * 将stream分离
     *
     * @return null|resource
     */
    public function detach()
    {
        if (!$this->isAttached()) {
            return null;
        }

        $oldResource = $this->stream;
        unset($this->stream);
        $this->metadata = [];
        $this->isSeekable = $this->isReadable = $this->isWritable = false;

        return $oldResource;
    }

    /**
     * 获取stream大小
     *
     * @return int|null
     */
    public function getSize()
    {
        if (!$this->isAttached()) {
            return null;
        }

        $stat = fstat($this->stream);

        return isset($stat['size']) ? $stat['size'] : null;
    }

    /**
     * 返回stream指针位置
     *
     * @return int
     * @throws \RuntimeException
     */
    public function tell()
    {
        if (false === $position = ftell($this->stream)) {
            throw new RuntimeException('Unable to get position of stream');
        }

        return $position;
    }

    /**
     * 检查是否到到了stream结束位置
     *
     * @return bool
     */
    public function eof()
    {
        // 如果不是资源就返回true
        return !$this->isAttached() || feof($this->stream);
    }

    /**
     * 检查是否可以定位
     *
     * @return bool
     */
    public function isSeekable()
    {
        return $this->isSeekable;
    }

    /**
     * 在stream中定位
     *
     * @param int $offset
     * @param int $whence
     * @throws RuntimeException.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable');
        }

        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException(sprintf(
                'Unable to seek to stream position %s with whence %s',
                $offset,
                var_export($whence, true)
            ));
        }
    }

    /**
     * 将stream指针的位置 设置为stream的开头
     *
     * @throws RuntimeException.
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * 是否可读
     *
     * @return bool
     */
    public function isReadable()
    {
        return $this->isReadable;
    }

    /**
     * 读取指定长度数据流
     *
     * @param int $length
     * @return string
     * @throws RuntimeException.
     */
    public function read($length)
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Cannot read from non-readable stream');
        }
        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }

        if (0 === $length) {
            return '';
        }

        //从stream指针的位置开始读取
        $data = stream_get_contents($this->stream, $length);

        if (false === $data) {
            throw new RuntimeException("Unable to read from stream");
        }

        return $data;
    }

    /**
     * 检查是否可写
     *
     * @return bool
     */
    public function isWritable()
    {
        return $this->isWritable;
    }

    /**
     * 写入字符串,返回写入长度
     *
     * @param $content $string
     * @return int
     */
    public function write($content)
    {
        if (
            !is_null($content) &&
            !is_string($content) &&
            !is_numeric($content) &&
            !method_exists($content,'__toString')
        ) {
            //参数错误
            throw new InvalidArgumentException(sprintf(
                'The Response content must be a string or object implementing __toString(), "%s" given.',
                gettype($content)
            ));
        }

        if (!$this->isWritable()) {
            //写入失败
            throw new RuntimeException('Cannot write to a non-writable stream');
        }

        if (($written = fwrite($this->stream, (string)$content)) === false) {
            throw new RuntimeException('Unable to write to stream');
        }

        return $written;
    }


    /**
     * 获取剩余数据流
     *
     * @return string
     * @throws RuntimeException if unable to read. (无法读取？为空还是读取失败？)
     */
    public function getContents()
    {
        $contents = stream_get_contents($this->stream);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    /**
     * 从封装协议文件指针中取得报头/元数据
     *
     * @param null $key
     * @return array|mixed|null
     */
    public function getMetadata($key = null)
    {
        if ($key === null) {
            return $this->metadata;
        }

        return isset($this->metadata[$key]) ? $this->metadata[$key] : null;
    }

    /**
     * 检查资源是否存在
     *
     * @return bool
     */
    public function isAttached()
    {
        return isset($this->stream) && is_resource($this->stream);
    }
}