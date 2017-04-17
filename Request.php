<?php
namespace Ant\Http;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\RequestInterface;

/**
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
     * @param mixed $body        Body内容
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

        $this->method = $method;
        $this->uri = $uri;
        $this->setHeaders($headers);
        $this->protocolVersion = $protocolVersion;

        // 如果没设置Host,尝试通过Uri获取
        if (!$this->hasHeader("host")) {
            $this->updateHostFromUri();
        }

        if ($body !== "" && $body !== null) {
            // Todo Lazy Stream
            $this->body = Body::createFrom($body);
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
        if ($size = $this->getBody()->getSize()) {
            //设置Body长度
            $this->headers['content-length'] = [$size];
        }

        $requestString = sprintf(
            "%s %s HTTP/%s\r\n",
            $this->getOriginalMethod(),
            $this->getRequestTarget(),
            $this->getProtocolVersion()
        );

        $requestString .= $this->headerToString();
        $requestString .= PHP_EOL;
        $requestString .= (string)$this->getBody();

        return $requestString;
    }

    /**
     * 获取重写后的http请求方式
     *
     * @return string
     */
    public function getMethod()
    {
        $method = $this->method;

        // 检查是否在报头中重载了http动词
        if ($customMethod = $this->getHeaderLine('x-http-method-override')) {
            $method = $customMethod;
        }

        // 当请求方式为Post时,检查是否为表单提交,跟请求重写
        if ($this->method == 'POST' && $customMethod = $this->getBodyParam('_method')) {
            $method = $customMethod;
        }

        return strtoupper($method);
    }

    /**
     * 获取原始的http请求方式
     *
     * @return string
     */
    public function getOriginalMethod()
    {
        return $this->method;
    }

    /**
     * 设置请求方式
     *
     * @param string $method
     * @return Request
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
     * @return Request
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

        parse_str($query, $this->queryParams);

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
     * @return Request
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $self = $this->changeAttribute('uri', $uri);
        parse_str($uri->getQuery(), $self->queryParams);

        // 如果开启host保护,原Host为空且新Uri包含Host时才更新
        if (!$preserveHost) {
            $host = explode(',', $uri->getHost());
        } elseif ((!$this->hasHeader('host') || empty($this->getHeaderLine('host'))) && $uri->getHost() !== '') {
            $host = explode(',', $uri->getHost());
        }

        if (isset($host)) {
            $self->updateHostFromUri();
        }

        return $self;
    }

    /**
     * 检查请求方式
     *
     * @param $method
     * @return bool
     */
    public function isMethod($method)
    {
        return $this->getMethod() === $method;
    }

    /**
     * 查看是否是GET请求
     *
     * @return bool
     */
    public function isGet()
    {
        return $this->isMethod('GET');
    }

    /**
     * 查看是否是POST请求
     *
     * @return bool
     */
    public function isPost()
    {
        return $this->isMethod('POST');
    }

    /**
     * 查看是否是PUT请求
     *
     * @return bool
     */
    public function isPut()
    {
        return $this->isMethod('PUT');
    }

    /**
     * 查看是否是DELETE请求
     *
     * @return bool
     */
    public function isDelete()
    {
        return $this->isMethod('DELETE');
    }

    /**
     * 检查是否是异步请求
     * 注意 : 主流JS框架发起AJAX都有此参数,如果是原生AJAX需要手动添加到http头
     *
     * @return bool
     */
    public function isAjax()
    {
        return strtolower($this->getHeaderLine('x-requested-with')) === 'xmlhttprequest';
    }

    /**
     * 通过Uri更新请求主机名
     */
    protected function updateHostFromUri()
    {
        $host = $this->uri->getHost();

        if ($host == '') {
            return;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        $this->headers['host'] = [$host];
    }
}