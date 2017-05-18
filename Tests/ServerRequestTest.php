<?php
namespace Test;

use Ant\Http\Uri;
use Ant\Http\ServerRequest;
use Ant\Http\CliServerRequest;

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
        $requestString = file_get_contents(__DIR__ . "/fixtures/BodyIsForm.txt");

        $request = CliServerRequest::createFromString($requestString);
        $this->assertEquals($requestString, $request->__toString());
        $this->assertInstanceOf(ServerRequest::class, $request);
    }

    public function testHeaderOverrideRequestMethod()
    {
        $requestString =
            "GET /Test HTTP/1.1\r\n".
            "X-Http-Method-Override: PATCH\r\n".
            "Host: www.example.com\r\n\r\n";

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
        $requestString =
            "POST /Test HTTP/1.1\r\n".
            "Content-Type: application/json\r\n".
            "Host: www.example.com\r\n\r\n".
            "{\"_method\":\"DELETE\"}";

        //================= 当请求为POST的时候尝试用post参数重写请求方法 =================//
        $request = CliServerRequest::createFromString($requestString);
        $this->assertEquals("DELETE", $request->getMethod());
        $this->assertEquals("POST", $request->getOriginalMethod());
        $this->assertEquals("DELETE", $request->getBodyParam('_method'));
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

    public function testRequestQueryParams()
    {
        $request = $this->createRequest();
        $this->assertEquals(['key' => 'value'], $request->getQueryParams());

        // 修改Get参数
        $newRequest = $request->withQueryParams(['foo' => 'bar']);
        $this->assertEquals('/Test?foo=bar#hello', $newRequest->getRequestTarget());
        $this->assertEquals(['foo' => 'bar'], $newRequest->getQueryParams());

        // 修改请求目标
        $newRequest = $request->withRequestTarget('/Demo?name=alex&age=18');
        $this->assertEquals(['name' => 'alex','age' => '18'], $newRequest->getQueryParams());

        // 修改Uri
        $newRequest = $request->withUri(new Uri('http://www.domain.com/foobar?test_key=test_value'));
        $this->assertEquals(['test_key' => 'test_value'], $newRequest->getQueryParams());
    }

    /**
     * 当请求的Body类型为Json时的解析结果
     */
    public function testContentTypeIsJson()
    {
        $filename = __DIR__ . "/fixtures/BodyIsJson.txt";

        if (file_exists($filename) && is_readable($filename)) {
            $request = CliServerRequest::createFromString(
                file_get_contents($filename)
            );

            $this->assertTrue(is_array($request->getParsedBody()));
            $this->assertEquals($request->getBodyParam('data'), [
                [
                    "name"  =>  "anlun",
                    "age"   =>  "20",
                    "sex"   =>  "male"
                ],
                [
                    "name"  =>  "annie",
                    "age"   =>  "23",
                    "sex"   =>  "female"
                ]
            ]);
        }
    }

    /**
     * 当请求的Body类型为Json时的解析结果
     */
    public function testContentTypeIsXml()
    {
        $filename = __DIR__ . "/fixtures/BodyIsXml.txt";

        if (file_exists($filename) && is_readable($filename)) {
            $request = CliServerRequest::createFromString(
                file_get_contents($filename)
            );

            $this->assertEquals('bar', $request->getBodyParam('foo'));
            $this->assertEquals('bae', $request->getBodyParam('fii'));
            $this->assertInstanceOf(\SimpleXMLElement::class, $request->getParsedBody());
        }
    }

    /**
     * 当请求的body类型为urlencode时的解析结果
     */
    public function testContentTypeIsUrlEncode()
    {
        $filename = __DIR__ . "/fixtures/BodyIsUrlEncode.txt";

        if (file_exists($filename) && is_readable($filename)) {
            $request = CliServerRequest::createFromString(
                file_get_contents($filename)
            );

            $this->assertEquals('bar', $request->getBodyParam('foo'));
            $this->assertEquals('bae', $request->getBodyParam('fii'));
            $this->assertEquals(['foo' => 'bar','fii' => 'bae'], $request->getParsedBody());
        }
    }

    /**
     * 测试解析表单数据
     */
    public function testBodyIsForm()
    {
        $filename = __DIR__ . "/fixtures/BodyIsForm.txt";

        if (file_exists($filename) && is_readable($filename)) {
            $request = CliServerRequest::createFromString(
                file_get_contents($filename)
            );

            $this->assertEquals('bar', $request->getBodyParam('foo'));

            $file = $request->getUploadedFiles()['file-test'];

            $this->assertInstanceOf(\Psr\Http\Message\UploadedFileInterface::class, $file);
        }
    }
}