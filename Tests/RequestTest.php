<?php
namespace Test;

use Ant\Http\Uri;
use Ant\Http\Request;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Psr\Http\Message\RequestInterface
     */
    protected $request;

    public function setUp()
    {
        $this->request = new Request("GET", "http://www.example.com/Test?key=value#hello");
    }

    public function testRequestMethod()
    {
        $this->assertEquals($this->request->getMethod(), "GET");

        $request = $this->request->withMethod("POST");

        $this->assertEquals($request->getMethod(), "POST");
        $this->assertNotEquals($this->request->getMethod(), "POST");
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRequestUri()
    {
        //================================== 测试Uri ==================================//
        // 获取Uri
        $this->assertEquals('/Test', $this->request->getUri()->getPath());
        $this->assertEquals('/Test?key=value#hello', $this->request->getRequestTarget());
        $this->assertEquals('http://www.example.com:80/Test?key=value#hello', (string)$this->request->getUri());

        //================================== 测试请求目标对GET参数与Uri的影响 ==================================//
        // 修改请求目标不应该影响主机名
        $newRequest = $this->request->withRequestTarget('http://test.com/Demo?name=alex&age=18');
        $this->assertEquals('/Demo', $newRequest->getUri()->getPath());
        $this->assertEquals('/Demo?name=alex&age=18', $newRequest->getRequestTarget());
        $this->assertEquals('http://www.example.com:80/Demo?name=alex&age=18', (string)$newRequest->getUri());
        $this->assertEquals("www.example.com:80", $newRequest->getHeaderLine("Host"));

        //================================== 测试Uri对请求目标的影响 ==================================//
        // 修改Uri
        $newRequest = $this->request->withUri(new Uri('http://www.domain.com/foobar?test_key=test_value'));
        $this->assertEquals('/foobar?test_key=test_value', $newRequest->getRequestTarget());
        $this->assertEquals('/foobar', $newRequest->getUri()->getPath());
        $this->assertEquals('www.domain.com:80', $newRequest->getHeaderLine("Host"));

        //================================== 测试输入非法参数 ==================================//
        $this->request->withRequestTarget(['test']);
    }
}