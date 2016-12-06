<?php
namespace Ant\Http\Test;

use Ant\Http\Request;
use Ant\Http\Uri;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return Request
     */
    public function createRequest()
    {
        $requestString = <<<EOT
GET /Test?key=value#hello HTTP/1.1
Host: www.example.com
Accept: application/json
Cookie: foo=bar;test_key=test_value\r\n\r\n
EOT;

        return Request::createFromRequestStr($requestString);
    }

    public function testCreateRequestFromString()
    {
        $requestString = "GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n";

        $request = Request::createFromRequestStr($requestString);
        $this->assertEquals($requestString,$request->__toString());
        $this->assertInstanceOf(Request::class,$request);
    }

    public function testGetMethodAntWithMethod()
    {
        $request = $this->createRequest();

        //=================== Http动词是否为GET ===================//
        $this->assertEquals('GET',$request->getMethod());
        $this->assertTrue($request->isGet());
        $this->assertFalse($request->isPost());

        //=================== 修改HTTP动词后与原来的区别 ===================//
        $newRequest = $request->withMethod('POST');
        $this->assertEquals('GET',$request->getMethod());
        $this->assertEquals('POST',$newRequest->getMethod());
        $this->assertNotEquals($newRequest,$request);
    }

    public function testHeaderOverrideRequestMethod()
    {
        $requestString = <<<EOT
GET /Test HTTP/1.1
X-Http-Method-Override: PATCH
Host: www.example.com\r\n\r\n
EOT;

        //================= 在Http头部重写请求方法后，是否会替换原来的请求方法 =================//
        $request = Request::createFromRequestStr($requestString);
        $this->assertEquals("PATCH",$request->getMethod());
        $this->assertEquals("PATCH",$request->getHeaderLine('X-Http-Method-Override'));

        //======================== 尝试在头部重写Http动词 ========================//
        $newRequest = $request->withHeader('X-Http-Method-Override','DELETE');
        $this->assertNotEquals("DELETE",$newRequest->getMethod());
        $this->assertEquals("PATCH",$newRequest->getMethod());
    }

    public function testBodyParamOverrideRequestMethod()
    {
        $requestString = <<<EOT
POST /Test HTTP/1.1
Content-Type: application/json
Host: www.example.com\r\n\r\n
{"_method":"DELETE"}
EOT;

        //================= 当请求为POST的时候尝试用post参数重写请求方法 =================//
        $request = Request::createFromRequestStr($requestString);
        $this->assertEquals("DELETE",$request->getMethod());
        $this->assertEquals("DELETE",$request->getBodyParam('_method'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRequestUri()
    {
        //================================== 测试Uri ==================================//
        $request = $this->createRequest();
        //获取Uri
        $this->assertEquals('/Test',$request->getRequestBasePath());
        $this->assertEquals('/Test?key=value#hello',$request->getRequestTarget());
        $this->assertEquals('http://www.example.com:80/Test?key=value#hello',(string)$request->getUri());
        $this->assertEquals(['key' => 'value'],$request->getQueryparams());

        //================================== 测试请求目标对GET参数与Uri的影响 ==================================//
        //修改请求目标
        $newRequest = $request->withRequestTarget('/Demo?name=alex&age=18');
        $this->assertEquals('/Demo',$newRequest->getRequestBasePath());
        $this->assertEquals('/Demo?name=alex&age=18',$newRequest->getRequestTarget());
        $this->assertEquals('http://www.example.com:80/Demo?name=alex&age=18',(string)$newRequest->getUri());
        $this->assertEquals(['name' => 'alex','age' => '18'],$request->getQueryParams());

        //================================== 测试GET参数对请求Uri的影响 ==================================//
        //修改Get参数
        $newRequest = $request->withQueryParams(['foo' => 'bar']);
        $this->assertEquals('/Test?foo=bar#hello',$newRequest->getRequestTarget());
        $this->assertEquals(['foo' => 'bar'],$newRequest->getQueryParams());
        $this->assertEquals('http://www.example.com:80/Test?foo=bar#hello',(string)$newRequest->getUri());

        //================================== 测试Uri对请求目标的影响 ==================================//
        //修改Uri
        $newRequest = $request->withUri(new Uri('http://www.domain.com/foobar?test_key=test_value'));
        $this->assertEquals('/foobar?test_key=test_value',$newRequest->getRequestTarget());
        $this->assertEquals('/foobar',$newRequest->getRequestBasePath());
        $this->assertEquals(['test_key' => 'test_value'],$newRequest->getQueryParams());

        //================================== 测试输入非法参数 ==================================//
        $request->withRequestTarget(['test']);
    }

    public function testRequestCookie()
    {
        //================================== 查看请求Cookie ==================================//
        $request = $this->createRequest();
        $cookie = $request->getCookieParams();
        $this->assertTrue(array_key_exists('foo',$cookie));
        $this->assertEquals('bar',$cookie['foo']);
        $this->assertEquals(['foo' => 'bar','test_key' => 'test_value'],$cookie);

        //================================== 设置请求Cookie ==================================//
        $cookie = [
            'foo'       =>  'bar',
            'key'       =>  'value',
            'PHPSESSID' => 'test'
        ];

        $newRequest = $request->withCookieParams($cookie);
        $this->assertEquals($cookie,$newRequest->getCookieParams());
        $this->assertEquals("GET /Test?key=value#hello HTTP/1.1\r\nHost: www.example.com\r\nAccept: application/json\r\nCookie: foo=bar; key=value; PHPSESSID=test\r\n\r\n",(string)$newRequest);
    }
}