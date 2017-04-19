<?php
namespace Ant\Http;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

/**
 * Class Uri
 * @package Ant\Http
 */
class Uri implements UriInterface
{
    /**
     * 标准端口
     *
     * @var array
     */
    protected $standardPort = [
        'http'  =>  80,
        'https' =>  443,
    ];

    /**
     * 协议
     *
     * @var string
     */
    protected $scheme = "http";

    /**
     * 请求主机地址
     *
     * @var string
     */
    protected $host = null;

    /**
     * 请求的端口号
     *
     * @var null|int
     */
    protected $port = null;

    /**
     * http用户
     *
     * @var string
     */
    protected $user = null;

    /**
     * http用户连接密码
     *
     * @var string
     */
    protected $pass = null;

    /**
     * 请求资源路径
     *
     * @var string
     */
    protected $path = null;

    /**
     * 查询参数
     *
     * @var string
     */
    protected $query = null;

    /**
     * 分段
     *
     * @var string
     */
    protected $fragment = null;

    /**
     * @return static
     */
    public static function createFromServerParams($serverParams)
    {
        $scheme = (isset($serverParams['HTTPS']) && $serverParams['HTTPS'] == 'on') ? 'https' : 'http';

        $username = isset($serverParams['PHP_AUTH_USER']) ? $serverParams['PHP_AUTH_USER'] : '';
        $password = isset($serverParams['PHP_AUTH_PW']) ? $serverParams['PHP_AUTH_PW'] : '';
        $userInfo = $username . ($password ? ':' . $password : '');

        //获取主机地址
        if (isset($serverParams['HTTP_HOST'])) {
            if (strpos($serverParams['HTTP_HOST'],':')) {
                list($host,$port) = explode(':',$serverParams['HTTP_HOST'],2);
                $port = (int)$port;
            } else {
                $host = $serverParams['HTTP_HOST'];
                $port = null;
            }
        } else {
            $host = isset($serverParams['SERVER_NAME'])
                ? $serverParams['SERVER_NAME']
                : (isset($serverParams['SERVER_ADDR']) ? $serverParams['SERVER_ADDR'] : null);

            $port = isset($serverParams['SERVER_PORT']) ? $serverParams['SERVER_PORT'] : null;
        }

        $uri = $scheme.':';
        $uri .= '//'.($userInfo ? $userInfo."@" : ''). $host .($port !== null ? ':' . $port : '');
        $uri .= isset($serverParams['REQUEST_URI']) ? $serverParams['REQUEST_URI'] : '';

        return new static($uri);
    }

    /**
     * @param string $uri
     * @param array $options
     */
    public function __construct($uri = '', array $options = [])
    {
        $options = array_merge((array) parse_url($uri), $options);

        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * 获取应用协议
     *
     * @return mixed
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * 指定连接方式
     *
     * @param string $scheme
     * @return UriInterface
     */
    public function withScheme($scheme)
    {
        $clone = clone $this;
        $clone->scheme = $scheme;

        return $clone;
    }

    /**
     * 获取授权信息,返回[user-info@]host[:port] user-info
     *
     * @return string
     */
    public function getAuthority()
    {
        $userInfo = $this->getUserInfo();
        $host = $this->getHost();
        $port = $this->getPort();

        return ($userInfo ? $userInfo."@" : ''). $host .($port !== null ? ':' . $port : '');
    }

    /**
     * 返回username[:password] password为可选
     *
     * @return string
     */
    public function getUserInfo()
    {
        return $this->user . ($this->pass ? ':' . $this->pass : '');
    }

    /**
     * 指定userInfo
     *
     * @param string $user
     * @param null $password
     * @return UriInterface
     */
    public function withUserInfo($user, $password = null)
    {
        $clone = clone $this;
        $clone->user = $user;
        $clone->pass = $password;

        return $clone;
    }

    /**
     * 获取host
     *
     * @return string.
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * 指定主机名
     *
     * @param string $host
     * @return UriInterface
     */
    public function withHost($host)
    {
        $clone = clone $this;
        $clone->host = $host;

        return $clone;
    }

    /**
     * 获取端口
     *
     * @return int|null
     */
    public function getPort()
    {
        if ($this->port) {
            return $this->port;
        }

        if (!$this->scheme) {
            return null;
        }

        return $this->port = $this->standardPort[$this->scheme];
    }

    /**
     * 指定端口
     *
     * @param int|null $port
     * @return UriInterface
     */
    public function withPort($port)
    {
        $port = $this->filterPort($port);
        $clone = clone $this;
        $clone->port = $port;

        return $clone;
    }

    /**
     * 过滤端口，确保参数为null或者int
     *
     * @param $port
     * @return int|null
     */
    protected function filterPort($port)
    {
        if (is_null($port) || (is_integer($port) && ($port >= 1 && $port <= 65535))) {
            return $port;
        }

        throw new InvalidArgumentException('Uri port must be null or an integer between 1 and 65535 (inclusive)');
    }

    /**
     * 获取脚本路径
     *
     * @return string
     */
    public function getPath()
    {
        return '/'.ltrim($this->path,'/');
    }

    /**
     * 指定Uri路径
     *
     * @param string $path
     * @return UriInterface
     */
    public function withPath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Uri path must be a string');
        }

        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }

    /**
     * 获取查询参数
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * 指定query参数
     *
     * @param mixed
     * @return UriInterface
     * @throws \InvalidArgumentException for invalid query strings
     */
    public function withQuery($query)
    {
        if (is_array($query)) {
            $query = http_build_query($query,'','&',PHP_QUERY_RFC3986);
        }

        if (!is_string($query) && !method_exists($query, '__toString')) {
            throw new InvalidArgumentException('Uri query must be a string');
        }

        $clone = clone $this;
        $clone->query = $query;

        return $clone;
    }

    /**
     * 获取 # 后的值
     *
     * @return string
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * 指定 # 后的参数
     *
     * @param string $fragment
     * @return Uri
     */
    public function withFragment($fragment)
    {
        if (!is_string($fragment)) {
            throw new \InvalidArgumentException('Uri fragment must be a string');
        }

        $clone = clone $this;
        $clone->fragment = $fragment;

        return $clone;
    }

    /**
     * 获取请求的目标
     *
     * @return string
     */
    public function getRequestTarget()
    {
        $requestTarget = $this->getPath();

        if ($query = $this->getQuery()) {
            $requestTarget .= '?'.$query;
        }

        if ($fragment = $this->getFragment()) {
            $requestTarget .= '#'.$fragment;
        }

        return $requestTarget;
    }

    /**
     * 输出完整链接
     *
     * @return string
     */
    public function __toString()
    {
        $uri = '';

        if ($scheme = $this->getScheme()) {
            $uri = $scheme.':';
        }

        if ($authority = $this->getAuthority()) {
            $uri .= '//'.$authority;
        } else {
            $uri = '';
        }

        return $uri.$this->getRequestTarget();
    }
}