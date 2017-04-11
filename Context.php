<?php
namespace Ant\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 上下文对象
 *
 * Class Context
 * @package Ant\Http
 */
class Context
{
    /**
     * @var RequestInterface
     */
    public $req;

    /**
     * @var ResponseInterface
     */
    public $res;

    /**
     * @param Request $request
     * @param Response $response
     */
    public function __construct(Request $request, Response $response)
    {
        $this->setRequest($request);
        $this->setResponse($response);
    }

    /**
     * @param RequestInterface $req
     */
    public function setRequest(RequestInterface $req)
    {
        $this->req = $this->packing($req);
    }

    /**
     * @param ResponseInterface $res
     */
    public function setResponse(ResponseInterface $res)
    {
        $this->res = $this->packing($res);
    }

    /**
     * 包装Http Message
     *
     * @param MessageInterface $psr7
     * @return Object
     */
    protected function packing(MessageInterface $message)
    {
        return new class($message)
        {
            protected $message;

            public function __construct(MessageInterface $message)
            {
                $this->message = $message;
            }

            public function __call($method, $args)
            {
                if (!method_exists($this->message, $method)) {
                    throw new \BadMethodCallException;
                }

                $result = $this->message->$method(...$args);

                if ($result instanceof MessageInterface) {
                    $this->message = $result;
                }

                return $result;
            }
        };
    }
}