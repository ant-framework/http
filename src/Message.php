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
     * 输出字符串
     *
     * @return string
     */
    abstract public function __toString();

    /**
     * 获取头部首行
     *
     * @return string
     */
    abstract protected function getStartLine();

    /**
     * 解析Http请求
     *
     * @param $message
     * @return array
     */
    public static function parseMessage($message)
    {
        if (!$message || !is_string($message)) {
            throw new InvalidArgumentException('Invalid message');
        }

        $lines = preg_split('/(\\r?\\n)/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

        $startLine = array_shift($lines);
        $headers = [];
        $body = '';

        // 跳过起始行的换行符,之后每读取一行,略过一个换行符
        // 如果读到空行,说明该行多出来一行换行符,视为Header结束,开始读取body
        for ($i = 1, $totalLines = count($lines); $i < $totalLines; $i += 2) {
            $line = $lines[$i];
            if (empty($line)) {
                if ($i < $totalLines - 1) {
                    // 将Body合并为字符串
                    $body = implode("", array_slice($lines, $i + 2));
                }
                break;
            }
            if (strpos($line, ':')) {
                list($name, $value) = array_map("trim", explode(':', $line, 2));
                $headers[$name][] = $value;
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
        return $this->hasHeader($name)
            ? $this->headers[strtolower($name)]
            : [];
    }

    /**
     * 返回一行header的值
     *
     * @param $name
     * @return string
     */
    public function getHeaderLine($name)
    {
        // 通过逗号分割的不区分大小写的字符串形式的所有值
        return $this->hasHeader($name)
            ? implode(',', $this->getHeader($name))
            : '';
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
                ? array_merge($this->getHeader($name), $value)
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
     * @param $headers array
     * @return self
     */
    public function withHeaders(array $headers = [])
    {
        $self = $this->immutability ? clone $this : $this;

        $headers = array_map(function ($header) {
            return is_array($header) ? $header : [$header];
        }, $headers);

        $self->headers = array_merge($self->headers, $headers);

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
            $this->body = new Body();
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
     * 输出Http头字符串
     *
     * @return string
     */
    public function headerToString()
    {
        $data = $this->getStartLine();

        foreach ($this->getHeaders() as $name => $value) {
            // 首字母大写,未来可能取消
            $name = implode('-', array_map('ucfirst', explode('-', $name)));
            $data .= sprintf("%s: %s\r\n", $name, implode(',', $value));
        }

        return $data;
    }

    /**
     * 设置Headers
     *
     * @param array $headers
     */
    protected function setHeaders(array $headers)
    {
        $this->headers = [];
        foreach ($headers as $name => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }

            $name = strtolower($name);
            $value = array_map("trim", $value);

            $this->headers[$name] = $value;
        }
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
     * Todo 参考setIn实现
     * 保持数据不变性
     *
     * @param $path string|array
     * @param $value mixed
     * @return object
     */
    protected function changeAttribute($path, $value)
    {
        $self = $this->immutability ? clone $this : $this;

        $path = is_array($path) ? $path : explode('.', $path);
        $array = &$self->{array_shift($path)};

        foreach ($path as $key) {
            $array = &$array[$key];
        }

        $array = $value;

        return $self;
    }
}