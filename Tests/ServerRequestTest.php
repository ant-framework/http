<?php
namespace Test;

use Ant\Http\CliServerRequest;
use Ant\Http\Uri;
use Ant\Http\ServerRequest;

// Todo 提高单元测试覆盖率
// Todo 提升单元测试代码质量
class ServerRequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return ServerRequest
     */
    public function createRequest()
    {
        $requestString =
            "GET /Test?key=value#hello HTTP/1.1\r\n".
            "Host: www.example.com\r\n".
            "Accept: application/json\r\n".
            "Cookie: foo=bar;test_key=test_value\r\n\r\n";

        return CliServerRequest::createFromString($requestString);
    }

    public function testCreateRequestFromString()
    {
        $requestString = file_get_contents(__DIR__."/fixtures/BodyIsForm.txt");

        $request = CliServerRequest::createFromString($requestString);
        $this->assertEquals($requestString, $request->__toString());
        $this->assertInstanceOf(ServerRequest::class, $request);
    }

    public function testGetMethodAntWithMethod()
    {
        $request = $this->createRequest();

        //=================== Http动词是否为GET ===================//
        $this->assertEquals('GET', $request->getMethod());
        $this->assertTrue($request->isGet());
        $this->assertFalse($request->isPost());

        //=================== 修改HTTP动词后与原来的区别 ===================//
        $newRequest = $request->withMethod('POST');
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('POST', $newRequest->getMethod());
        $this->assertNotEquals($newRequest, $request);
    }

    public function testHeaderOverrideRequestMethod()
    {
        $requestString = <<<EOT
GET /Test HTTP/1.1
X-Http-Method-Override: PATCH
Host: www.example.com\r\n\r\n
EOT;

        //================= 在Http头部重写请求方法后，是否会替换原来的请求方法 =================//
        $request = CliServerRequest::createFromString($requestString);
        $this->assertEquals("PATCH", $request->getMethod());
        $this->assertEquals("PATCH", $request->getHeaderLine('X-Http-Method-Override'));
        $this->assertEquals("GET", $request->getOriginalMethod());

        //======================== 尝试在头部重写Http动词 ========================//
        $newRequest = $request->withHeader('X-Http-Method-Override','DELETE');
        $this->assertEquals("DELETE", $newRequest->getMethod());
        $this->assertEquals("GET", $request->getOriginalMethod());
    }

    public function testBodyParamOverrideRequestMethod()
    {
        $requestString = <<<EOT
POST /Test HTTP/1.1
Content-Type: application/json
Host: www.example.com\r\n
{"_method":"DELETE"}
EOT;

        //================= 当请求为POST的时候尝试用post参数重写请求方法 =================//
        $request = CliServerRequest::createFromString($requestString);
        $this->assertEquals("DELETE", $request->getMethod());
        $this->assertEquals("POST", $request->getOriginalMethod());
        $this->assertEquals("DELETE", $request->getBodyParam('_method'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRequestUri()
    {
        //================================== 测试Uri ==================================//
        $request = $this->createRequest();
        //获取Uri
        $this->assertEquals('/Test', $request->getUri()->getPath());
        $this->assertEquals('/Test?key=value#hello', $request->getRequestTarget());
        $this->assertEquals('http://www.example.com:80/Test?key=value#hello', (string)$request->getUri());
        $this->assertEquals(['key' => 'value'], $request->getQueryparams());

        //================================== 测试请求目标对GET参数与Uri的影响 ==================================//
        //修改请求目标不应该影响主机名
        $newRequest = $request->withRequestTarget('http://test.com/Demo?name=alex&age=18');
        $this->assertEquals('/Demo', $newRequest->getUri()->getPath());
        $this->assertEquals('/Demo?name=alex&age=18', $newRequest->getRequestTarget());
        $this->assertEquals('http://www.example.com:80/Demo?name=alex&age=18', (string)$newRequest->getUri());
        $this->assertEquals(['name' => 'alex','age' => '18'], $newRequest->getQueryParams());
        $this->assertEquals("www.example.com", $newRequest->getHeaderLine("Host"));

        //================================== 测试GET参数对请求Uri的影响 ==================================//
        //修改Get参数
        $newRequest = $request->withQueryParams(['foo' => 'bar']);
        $this->assertEquals('/Test?foo=bar#hello', $newRequest->getRequestTarget());
        $this->assertEquals(['foo' => 'bar'], $newRequest->getQueryParams());
        $this->assertEquals('http://www.example.com:80/Test?foo=bar#hello', (string)$newRequest->getUri());

        //================================== 测试Uri对请求目标的影响 ==================================//
        //修改Uri
        $newRequest = $request->withUri(new Uri('http://www.domain.com/foobar?test_key=test_value'));
        $this->assertEquals('/foobar?test_key=test_value', $newRequest->getRequestTarget());
        $this->assertEquals('/foobar', $newRequest->getUri()->getPath());
        $this->assertEquals(['test_key' => 'test_value'], $newRequest->getQueryParams());
        $this->assertEquals('www.domain.com:80', $newRequest->getHeaderLine("Host"));

        //================================== 测试输入非法参数 ==================================//
        $request->withRequestTarget(['test']);
    }

    public function testRequestCookie()
    {
        //================================== 查看请求Cookie ==================================//
        $request = $this->createRequest();
        $cookie = $request->getCookieParams();
        $this->assertTrue(array_key_exists('foo',$cookie));
        $this->assertEquals('bar', $cookie['foo']);
        $this->assertEquals(['foo' => 'bar','test_key' => 'test_value'], $cookie);

        //================================== 设置请求Cookie ==================================//
        $cookie = [
            'foo'       =>  'bar',
            'key'       =>  'value',
            'PHPSESSID' => 'test'
        ];

        $newRequest = $request->withCookieParams($cookie);
        $this->assertEquals($cookie, $newRequest->getCookieParams());
        $this->assertEquals(
            "GET /Test?key=value#hello HTTP/1.1\r\n".
            "Host: www.example.com\r\n".
            "Accept: application/json\r\n".
            "Cookie: foo=bar; key=value; PHPSESSID=test\r\n\r\n",
            (string)$newRequest
        );
    }

    // Todo 测试解析Body文件

    /**
     * 当请求的Body类型为Json时的解析结果
     */
    public function testContentTypeIsJson()
    {
        $requestString = <<<EOT
POST /Test HTTP/1.1
Content-Type: application/json
Host: www.example.com\r\n
{"foo":"bar","fii":"bae"}
EOT;
        $request = CliServerRequest::createFromString($requestString);

        $this->assertEquals('bar', $request->getBodyParam('foo'));
        $this->assertEquals('bae', $request->getBodyParam('fii'));
        $this->assertEquals(['foo' => 'bar','fii' => 'bae'], $request->getParsedBody());
    }

    /**
     * 当请求的Body类型为Json时的解析结果
     */
    public function testContentTypeIsXml()
    {
        $requestString = <<<EOT
POST /Test HTTP/1.1
Content-Type: application/xml
Host: www.example.com\r\n
<?xml version="1.0"?>
<xml><foo>bar</foo><fii>bae</fii></xml>
EOT;
        $request = CliServerRequest::createFromString($requestString);

        $this->assertEquals('bar', $request->getBodyParam('foo'));
        $this->assertEquals('bae', $request->getBodyParam('fii'));
        $this->assertInstanceOf(\SimpleXMLElement::class, $request->getParsedBody());
    }

    /**
     * 当请求的body类型为urlencode时的解析结果
     */
    public function testContentTypeIsUrlEncode()
    {
        $requestString = <<<EOT
POST /Test HTTP/1.1
Content-Type: application/x-www-form-urlencoded
Host: www.example.com\r\n
foo=bar&fii=bae
EOT;
        $request = CliServerRequest::createFromString($requestString);

        $this->assertEquals('bar', $request->getBodyParam('foo'));
        $this->assertEquals('bae', $request->getBodyParam('fii'));
        $this->assertEquals(['foo' => 'bar','fii' => 'bae'], $request->getParsedBody());
    }

    /**
     * 测试解析表单数据
     */
    public function testBodyIsForm()
    {
        $requestString = <<<EOT
POST / HTTP/1.1
Host: 127.0.0.1:81
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryF7ujiYJ1r6fEQ1Qu\r\n
------WebKitFormBoundaryF7ujiYJ1r6fEQ1Qu
Content-Disposition: form-data; name="foo"

bar
------WebKitFormBoundaryF7ujiYJ1r6fEQ1Qu
Content-Disposition: form-data; name="fii"

bae
------WebKitFormBoundaryF7ujiYJ1r6fEQ1Qu--\r\n\r\n
EOT;
        $request = CliServerRequest::createFromString($requestString);

        $this->assertEquals('bar', $request->getBodyParam('foo'));
        $this->assertEquals('bae', $request->getBodyParam('fii'));
        $this->assertEquals(['foo' => 'bar','fii' => 'bae'], $request->getParsedBody());
    }

    /**
     * 测试修改body参数对request对象的影响
     */
    public function testToModifyTheEffectsOfBodyParams()
    {
        $request = (new ServerRequest('POST','http://www.example.com'))
            ->withHeader('Content-Type','application/json')
            ->withParsedBody([
                'foo'   =>  'bar'
            ]);

        $this->assertEquals('bar', $request->getBodyParam('foo'));
        $this->assertNotEquals("POST / HTTP/1.1\r\nHost: www.example.com:80\r\nContent-Type: application/json\r\n\r\n{\"foo\":\"bar\"}", $request->__toString());
        $this->assertEquals("POST / HTTP/1.1\r\nHost: www.example.com:80\r\nContent-Type: application/json\r\n\r\n", $request->__toString());
    }
}