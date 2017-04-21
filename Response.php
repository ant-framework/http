<?php
namespace Ant\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Todo 响应分块 dechex($len) . "\r\n" . $buffer . "\r\n";
 * Todo Cookie对象(包含加密,读取,设置响应Cookie)
 * Class Response
 * @package Ant\Http
 */
class Response extends Message implements ResponseInterface
{
    const SWITCHING_PROTOCOLS = 101;
    const PROCESSING = 102;
    const OK = 200;
    const CREATED = 201;
    const ACCEPTED = 202;
    const NON_AUTHORITATIVE_INFORMATION = 203;
    const NO_CONTENT = 204;
    const RESET_CONTENT = 205;
    const PARTIAL_CONTENT = 206;
    const MULTI_STATUS = 207;
    const ALREADY_REPORTED = 208;
    const MULTIPLE_CHOICES = 300;
    const MOVED_PERMANENTLY = 301;
    const FOUND = 302;
    const SEE_OTHER = 303;
    const NOT_MODIFIED = 304;
    const USE_PROXY = 305;
    const SWITCH_PROXY = 306;
    const TEMPORARY_REDIRECT = 307;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const PAYMENT_REQUIRED = 402;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const NOT_ACCEPTABLE = 406;
    const PROXY_AUTHENTICATION_REQUIRED = 407;
    const REQUEST_TIMEOUT = 408;
    const CONFLICT = 409;
    const GONE = 410;
    const LENGTH_REQUIRED = 411;
    const PRECONDITION_FAILED = 412;
    const REQUEST_ENTITY_TOO_LARGE = 413;
    const REQUEST_URI_TOO_LONG = 414;
    const UNSUPPORTED_MEDIA_TYPE = 415;
    const REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    const EXPECTATION_FAILED = 417;
    const IM_A_TEAPOT = 418;
    const UNPROCESSABLE_ENTITY = 422;
    const LOCKED = 423;
    const FAILED_DEPENDENCY = 424;
    const UNORDERED_COLLECTION = 425;
    const UPGRADE_REQUIRED = 426;
    const PRECONDITION_REQUIRED = 428;
    const TOO_MANY_REQUESTS = 429;
    const REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    const INTERNAL_SERVER_ERROR = 500;
    const NOT_IMPLEMENTED = 501;
    const BAD_GATEWAY = 502;
    const SERVICE_UNAVAILABLE = 503;
    const GATEWAY_TIMEOUT = 504;
    const HTTP_VERSION_NOT_SUPPORTED = 505;
    const VARIANT_ALSO_NEGOTIATES = 506;
    const INSUFFICIENT_STORAGE = 507;
    const LOOP_DETECTED = 508;
    const NETWORK_AUTHENTICATION_REQUIRED = 511;

    /**
     * http状态码与对应短语
     *
     * @var array
     */
    protected $httpReasonPhrase = [
        //1xx Informational 请求过程
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        //2xx Successful    请求成功
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        //3xx Redirection   请求重定向
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        //4xx Client Error  客户端请求出错
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        //5xx Server Error  服务器内部错误
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * http状态码
     *
     * @var int
     */
    protected $code;

    /**
     * http短语
     *
     * @var string
     */
    protected $responsePhrase = '';

    /**
     * 响应的cookie
     *
     * @var array
     */
    protected $cookieParams = [];

    /**
     * cookie默认值
     *
     * @var array
     */
    protected static $cookieDefaults = [
        // cookie名字
        'name'      =>  '',
        // cookie值
        'value'     =>  '',
        // 超时时间
        'expires'   =>  0,
        // cookie作用目录
        'path'      =>  '/',
        // cookie作用域名
        'domain'    =>  '',
        // 是否是host专属
        'hostonly'  =>  false,
        // 是否https专属
        'secure'    =>  false,
        // 除了发起Http请求外,Js是否可以使用cookie
        'httponly'  =>  false,
    ];

    /**
     * 通过http报头跟body信息创建一个response对象
     *
     * @param array $headerLines
     * @param $bodyBuffer
     * @return static
     *
     * @see http://php.net/manual/zh/reserved.variables.httpresponseheader.php
     */
    public static function createFromRequestResult(array $headerLines, $bodyBuffer = '')
    {
        //获取协议版本,响应码,响应短语
        list($protocol ,$statusCode, $responsePhrase) = explode(' ', array_shift($headerLines), 3);
        $protocol = str_replace('HTTP/', '', $protocol);

        // 获取http报头信息
        $headers = [];
        foreach ($headerLines as $line) {
            list($name, $value) = explode(':', $line, 2);
            $name = strtolower($name);

            $headers[$name] = array_key_exists($name, $headers)
                ? array_merge($headers[$name], [$value])
                : [$value];
        }

        // 创建Response实例
        return new static(
            $statusCode,
            $headers,
            Body::createFrom($bodyBuffer),
            $responsePhrase,
            $protocol
        );
    }

    /**
     * @param $receiveBuffer
     * @return Response
     */
    public static function createFromResponseStr($receiveBuffer)
    {
        if (!is_string($receiveBuffer)) {
            throw new \InvalidArgumentException('Response must be string');
        }

        list($headerBuffer, $bodyBuffer) = explode("\r\n\r\n", $receiveBuffer, 2);
        $headerLines = explode("\r\n",$headerBuffer);

        return static::createFromRequestResult($headerLines, $bodyBuffer);
    }

    /**
     * @param int $code
     * @param array $headers
     * @param StreamInterface|null $body
     * @param string $phrase
     * @param string $protocol
     */
    public function __construct(
        $code = 200,
        $headers = [],
        StreamInterface $body = null,
        $phrase = '',
        $protocol = '1.1'
    ) {
        $this->code = $code;
        $this->setHeaders($headers);
        $this->body = $body ? : new Body();
        $this->responsePhrase = $phrase;
        $this->protocolVersion = $protocol;

        if ($this->hasHeader('set-cookie')) {
            // Cookie与Header分开处理
            $this->extractCookieFromHeaders();
            unset($this->headers['set-cookie']);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->headerToString() . PHP_EOL . $this->getBody();
    }

    /**
     * 获取http状态码
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->code;
    }

    /**
     * 设置http状态码
     *
     * @param int $code
     * @param string $reasonPhrase
     * @return $this
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        if (!is_integer($code) || $code < 100 || $code > 599) {
            throw new InvalidArgumentException('Invalid HTTP status code');
        }

        $this->responsePhrase = (string) $reasonPhrase;

        return $this->changeAttribute('code', $code);
    }

    /**
     * 获取http响应短语
     *
     * @return null|string
     */
    public function getReasonPhrase()
    {
        if (empty($this->responsePhrase)) {
            $this->responsePhrase = isset($this->httpReasonPhrase[$this->code])
                ? $this->httpReasonPhrase[$this->code]
                : null;
        }

        return $this->responsePhrase;
    }

    /**
     * Todo 遵循Psr7的不变性
     * 设置Cookie
     * 同一个域名,不同路径允许有多个同名Cookie
     * 如果域名不同,路径相同,也可以重名Cookie
     * 同一个域名,同一个路径,不允许有重名Cookie
     *
     * @param $name
     * @param $value
     * @param array $options
     */
    public function setCookie($name, $value, array $options = [])
    {
        $options = array_merge(compact("name", "value"), $options);
        $options = array_replace(static::$cookieDefaults, $options);
        $key = sprintf('%s@%s:%s', $options['domain'], $options['path'], $name);

        // 保存cookie
        $this->cookieParams[$key] = $options;
    }

    /**
     * 获取指定域名路径下的Cookie
     *
     * @param $name
     * @param string $domain
     * @param string $path
     * @return string
     */
    public function getCookie($name, $domain = '', $path = '/')
    {
        $key = sprintf('%s@%s:%s', $name, $domain, $path);

        if (array_key_exists($key, $this->cookieParams)) {
            return $this->cookieParams[$key];
        }

        return '';
    }

    /**
     * @return array
     */
    public function getCookies()
    {
        return $this->cookieParams;
    }

    /**
     * http重定向
     *
     * @param $url
     * @param $status 303
     * @return Response
     */
    public function redirect($url, $status = 303)
    {
        return $this
            ->withStatus($status)
            ->withHeader('Location', $url);
    }

    /**
     * 根据状态码判断响应是否空内容
     *
     * @return bool
     */
    public function isEmpty()
    {
        return in_array($this->getStatusCode(),[204,205,304]);
    }

    /**
     * 是否为临时响应
     *
     * @return bool
     */
    public function isInformational()
    {
        return $this->getStatusCode() >= 100 && $this->getStatusCode() < 200;
    }

    /**
     * 是否成功
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->getStatusCode() >= 200 && $this->getStatusCode() < 300;
    }

    /**
     * 是否进行重定向
     *
     * @return bool
     */
    public function isRedirect()
    {
        return in_array($this->getStatusCode(), [301, 302, 303, 307]);
    }

    /**
     * 是否为重定向响应
     *
     * @return bool
     */
    public function isRedirection()
    {
        return $this->getStatusCode() >= 300 && $this->getStatusCode() < 400;
    }

    /**
     * 是否为拒绝请求
     *
     * @return bool
     */
    public function isForbidden()
    {
        return $this->getStatusCode() === 403;
    }

    /**
     * 是否找到页面
     *
     * @return bool
     */
    public function isNotFound()
    {
        return $this->getStatusCode() === 404;
    }

    /**
     * 是否为客户端错误
     *
     * @return bool
     */
    public function isClientError()
    {
        return $this->getStatusCode() >= 400 && $this->getStatusCode() < 500;
    }

    /**
     * 是否为服务端错误
     *
     * @return bool
     */
    public function isServerError()
    {
        return $this->getStatusCode() >= 500 && $this->getStatusCode() < 600;
    }

    /**
     * @return string
     */
    public function headerToString()
    {
        return parent::headerToString() . $this->getCookieHeader();
    }

    /**
     * @return string
     */
    protected function getStartLine()
    {
        return sprintf(
            "HTTP/%s %s %s\r\n",
            $this->getProtocolVersion(),
            $this->getStatusCode(),
            $this->getReasonPhrase()
        );
    }

    /**
     * 提取响应Cookie
     */
    protected function extractCookieFromHeaders()
    {
        foreach ($this->getHeader('set-cookie') as $value) {
            $cookie = explode(';', $value);
            list($name, $value) = array_map("trim", explode('=', array_shift($cookie)));

            $options = [];
            foreach ($cookie as $param) {
                // 辨别 hostonly,secure,httponly 等参数
                if (false !== strpos($param, '=')) {
                    list($key, $value) = array_map('trim', explode('=', $param));
                    $options[$key] = $value;
                } else {
                    $options[$param] = true;
                }
            }

            $this->setCookie($name, $value, $options);
        }
    }

    /**
     * @return string
     */
    protected function getCookieHeader()
    {
        $setCookies = [];
        // 获取所有cookie参数
        foreach ($this->getCookies() as $properties) {
            $cookie = [];

            // 设置cookie参数 PHP_QUERY_RFC3986
            $cookie[] = http_build_query([$properties['name'] => $properties['value']]);

            // 设置允许访问cookie的路径
            $cookie[] = 'path=' . $properties['path'];

            // 设置允许访问cookie的域名
            if ($properties['domain']) {
                $cookie[] = 'domain=' . $properties['domain'];
            }

            // 设置cookie过期时间
            if ($properties['expires'] !== 0) {
                if (is_string($properties['expires'])) {
                    $timestamp = strtotime($properties['expires']);
                } else {
                    $timestamp = (int) $properties['expires'];
                }

                $cookie[] = 'expires=' . gmdate('D, d-M-Y H:i:s e', $timestamp);
            }

            // 是否https专属
            if ($properties['secure'] === true) {
                $cookie[] = 'secure';
            }

            // 设置host专属
            if ($properties['hostonly'] === true) {
                $cookie[] = 'hostonly';
            }

            // 设置Http专属
            if ($properties['httponly'] === true) {
                $cookie[] = 'httponly';
            }

            $setCookies[] = sprintf("Set-Cookie: %s", implode('; ',$cookie));
        }

        return $setCookies ? implode(PHP_EOL, $setCookies) . PHP_EOL : '';
    }
}