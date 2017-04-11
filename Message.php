<?php
namespace Ant\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\MessageInterface;

/**
 * Class Message
 * @package Ant\Http
 */
abstract class Message implements MessageInterface
{
    /**
     * @var bool 是否保持数据不变性
     */
    protected $immutability = true;

    /**
     * @var string HTTP版本号
     */
    protected $protocolVersion = '1.1';

    /**
     * HTTP头信息
     *
     * @var array
     */
    protected $headers = [];

    /**
     * body信息
     *
     * @var StreamInterface
     */
    protected $body = null;

    /**
     * 属性
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * 输出字符串
     *
     * @return string
     */
    abstract public function __toString();

    /**
     * 解析Http请求
     *
     * @param $message
     * @return array
     */
    public static function parseMessage($message)
    {
        if (!$message || !is_string($message)) {
            throw new \InvalidArgumentException('Invalid message');
        }

        list($headerBuffer, $body) = explode("\r\n\r\n", $message, 2);

        $headerLines = explode("\r\n", $headerBuffer);

        $startLine = array_shift($headerLines);

        $headers = [];
        foreach ($headerLines as $line) {
            if (strpos($line, ':')) {
                $parts = explode(':', $line, 2);
                $key = strtolower($parts[0]);
                // 通过逗号分割的不区分大小写的字符串形式的所有值
                $value = array_map("trim", explode(',', $parts[1]));

                if (isset($headers[$key])) {
                    $headers[$key] = array_merge($headers[$key], $value);
                } else {
                    $headers[$key] = $value;
                }
            }
        }

        return [$startLine, $headers, $body];
    }

    /**
     * 获取HTTP协议版本
     *
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * 设置HTTP协议版本
     *
     * @param $version
     * @return Message
     */
    public function withProtocolVersion($version)
    {
        if (!is_string($version) && !is_int($version) && !is_double($version)) {
            throw new InvalidArgumentException('Input version error');
        }

        return $this->changeAttribute('protocolVersion', $version);
    }

    /**
     * 获取HTTP Header
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }


    /**
     * 检查header是否存在
     *
     * @param $name
     * @return bool
     */
    public function hasHeader($name)
    {
        $name = strtolower($name);

        return array_key_exists($name, $this->headers);
    }

    /**
     * 返回指定header数组
     *
     * @param $name
     * @return array
     */
    public function getHeader($name)
    {
        $name = strtolower($name);

        if (!$this->hasHeader($name)) {
            return [];
        }

        return $this->headers[$name];
    }

    /**
     * 返回一行header的值
     *
     * @param $name
     * @return string
     */
    public function getHeaderLine($name)
    {
        $value = $this->getHeader($name);

        return !empty($value) ? implode(',',$value) : '';
    }

    /**
     * 替换之前header
     *
     * @param $name
     * @param $value
     * @return self
     */
    public function withHeader($name, $value)
    {
        if (!is_array($value) && !is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('Header must be string or array');
        }

        return $this->changeAttribute(
            ['headers', strtolower($name)],
            is_array($value) ? $value : explode(',',$value)
        );
    }

    /**
     * 向header添加信息
     *
     * @param $name  string
     * @param $value string|array
     * @return self
     */
    public function withAddedHeader($name, $value)
    {
        if ($this->hasHeader($name)) {
            $value = (is_array($value))
                ? array_merge($this->getHeader($name),$value)
                : implode(',',$this->getHeader($name)).','.$value;
        }

        return $this->withHeader($name, $value);
    }

    /**
     * 销毁header信息
     *
     * @param $name
     * @return self
     */
    public function withoutHeader($name)
    {
        if (!$this->hasHeader($name)) {
            return $this;
        }
        $header = $this->headers;
        unset($header[strtolower($name)]);

        return $this->changeAttribute('headers', $header);
    }

    /**
     * 通过迭代的方式添加响应头
     *
     * @param $iterator \Iterator|array
     * @return self
     */
    public function addHeaderFromIterator($iterator)
    {
        if (!$iterator instanceof \Iterator && !is_array($iterator)) {
            throw new \RuntimeException('Arguments must be an iterator');
        }

        $self = $this;
        foreach ($iterator as $name => $value) {
            $self = $self->withAddedHeader($name,$value);
        }

        return $self;
    }

    /**
     * 获取body
     *
     * @return StreamInterface
     */
    public function getBody()
    {
        if (!$this->body) {
            // Todo Lazy Init Body
        }

        return $this->body;
    }

    /**
     * 添加body数据
     *
     * @param StreamInterface $body
     * @return $this|Message
     */
    public function withBody(StreamInterface $body)
    {
        if ($body === $this->body) {
            return $this;
        }

        return $this->changeAttribute('body', $body);
    }

    /**
     * 获取所有属性
     *
     * @return mixed[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * 获取一个属性的值
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes)
            ? $this->attributes[$name]
            : $default;
    }

    /**
     * 设置一个属性.
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function withAttribute($name, $value)
    {
        return $this->changeAttribute(['attributes',$name], $value);
    }

    /**
     * 删除一个属性
     *
     * @see getAttributes()
     * @param string $name .
     * @return self
     */
    public function withoutAttribute($name)
    {
        $self = clone $this;
        if (array_key_exists($name, $self->attributes)) {
            unset($self->attributes[$name]);
        }

        return $self;
    }

    /**
     * 设置对象不变性
     * 根据PSR-7的接口要求
     * 每次修改请求内容或者响应内容
     * 都要保证原有数据不能被覆盖
     * 所以在改变了一项属性的时候需要clone一个相同的类
     * 去改变那个相同的类的属性，通过这种方式保证原有数据不被覆盖
     * 本人出于损耗与易用性，给这个保持不变性加上了一个开关
     *
     * @param bool|true $enable
     * @return self
     * @see http://www.php-fig.org/psr/psr-7/meta/
     */
    public function keepImmutability($enable = true)
    {
        $this->immutability = $enable;

        return $this;
    }

    /**
     * 输出Http头字符串
     *
     * @return string
     */
    public function headerToString()
    {
        $result = [];

        foreach ($this->getHeaders() as $headerName => $headerValue) {
            if (is_array($headerValue)) {
                $headerValue = implode(',', $headerValue);
            }

            $headerName = implode('-',array_map('ucfirst',explode('-',$headerName)));
            $result[] = sprintf('%s: %s',$headerName,$headerValue);
        }

        return $result ? implode(PHP_EOL,$result).PHP_EOL : '';
    }

    /**
     * 保持数据不变性
     *
     * @param $attribute string
     * @param $value mixed
     * @return object
     */
    protected function changeAttribute($attribute, $value)
    {
        $self = $this->immutability ? clone $this : $this;

        if (is_array($attribute)) {
            list($attribute, $key) = $attribute;

            // 兼容7以下
            $attribute = &$self->$attribute;
            $attribute[$key] = $value;
        } else {
            $self->$attribute = $value;
        }

        return $self;
    }
}