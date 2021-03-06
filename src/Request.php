<?php
namespace Ant\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Todo 单元测试
 * Class Request
 * @package Ant\Http
 * @see http://www.php-fig.org/psr/psr-7/
 */
class Request extends Message implements RequestInterface
{
    /**
     * http 请求方式
     *
     * @var string
     */
    protected $method = null;

    /**
     * Uri 实例
     *
     * @var \Psr\Http\Message\UriInterface
     */
    protected $uri = null;

    /**
     * Request constructor.
     * @param string $method                    Http动词
     * @param string $uri                       请求的Uri
     * @param array $headers                    Http头
     * @param mixed $body                       Body内容
     * @param string $protocolVersion           Http协议版本
     */
    public function __construct(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $protocolVersion = '1.1'
    ) {
        if (!$uri instanceof UriInterface) {
            $uri = new Uri($uri);
        }

        if ($body != "" && $body !== null) {
            $body = Stream::createFrom($body);
        }

        $this->method = $method;
        $this->uri = $uri;
        $this->setHeaders($headers);
        $this->protocolVersion = $protocolVersion;
        $this->body = $body;

        // 如果没设置Host,尝试通过Uri获取
        if (!$this->hasHeader("host")) {
            $this->updateHostFromUri();
        }
    }

    /**
     * Todo::将文件写入Body
     * 以字符串的形式输出请求
     *
     * @return string
     */
    public function __toString()
    {
        return $this->headerToString() . "\r\n" . $this->body;
    }

    /**
     * 获取重写后的http请求方式
     *
     * @return string
     */
    public function getMethod()
    {
        return strtoupper($this->method);
    }

    /**
     * 设置请求方式
     *
     * @param string $method
     * @return self
     */
    public function withMethod($method)
    {
        return $this->changeAttribute('method', $method);
    }

    /**
     * 获取请求目标(资源)
     *
     * @return string
     */
    public function getRequestTarget()
    {
        $requestTarget = $this->uri->getPath();

        if ($query = $this->uri->getQuery()) {
            $requestTarget .= '?'.$query;
        }

        if ($fragment = $this->uri->getFragment()) {
            $requestTarget .= '#'.$fragment;
        }

        return $requestTarget;
    }

    /**
     * 设置请求资源
     *
     * @param mixed $requestTarget
     * @return self
     */
    public function withRequestTarget($requestTarget)
    {
        if (!is_string($requestTarget)) {
            throw new InvalidArgumentException('The request target must be a string');
        }

        $meta = parse_url($requestTarget);

        $path = isset($meta['path']) ? $meta['path'] : '';
        $query = isset($meta['query']) ? $meta['query'] : '';
        $fragment = isset($meta['fragment']) ? $meta['fragment'] : '';

        $uri = $this->uri
            ->withPath($path)
            ->withQuery($query)
            ->withFragment($fragment);

        // 启用Host保护,修改请求目标,不应该影响Host
        return $this->withUri($uri, true);
    }

    /**
     * 获取URI
     *
     * @return UriInterface
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * 设置uri
     *
     * @param UriInterface $uri
     * @param bool|false $preserveHost
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $self = $this->changeAttribute('uri', $uri);

        // 关闭了Host保护,或者主机名不存在时更新HOST
        if (!$preserveHost || !isset($this->headers['host'])) {
            $self->updateHostFromUri();
        }

        return $self;
    }

    /**
     * 检查是否是异步请求
     * 需要js在发起Http请求时,在header中标识 X-Request-With: xmlhttprequest
     *
     * @return bool
     */
    public function isAjax()
    {
        return strtolower($this->getHeaderLine('x-requested-with')) === 'xmlhttprequest';
    }

    /**
     * @return string
     */
    public function headerToString()
    {
        if ($this->getMethod() !== "GET" && !$this->hasHeader("Content-Length")) {
            // 设置Body长度
            $this->headers['content-length'] = [$this->getBody()->getSize()];
        }

        return parent::headerToString();
    }

    /**
     * 通过Uri更新请求主机名
     */
    protected function updateHostFromUri()
    {
        $host = $this->uri->getHost();

        // Uri包含主机名时更新
        if ($host == '') {
            return;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        $this->headers['host'] = [$host];
    }

    /**
     * @return string
     */
    protected function getStartLine()
    {
        return sprintf(
            "%s %s HTTP/%s\r\n",
            $this->getMethod(),
            $this->getRequestTarget(),
            $this->getProtocolVersion()
        );
    }
}