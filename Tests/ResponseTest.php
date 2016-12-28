<?php
namespace Ant\Http\Test;

use Ant\Http\Response;
use Ant\Http\Stream;

class ResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testResponse()
    {
        $response = new Response();
        $this->assertEquals("HTTP/1.1 200 OK\r\n\r\n",$response->__toString());
    }

    public function testHttpStatusCode()
    {
        $response = (new Response(404,[],null,"is null"))->keepImmutability(false);
        $this->assertEquals('is null',$response->getReasonPhrase());
        $this->assertEquals(404,$response->getStatusCode());

        $response->withStatus(403);
        $this->assertEquals(403,$response->getStatusCode());
        $this->assertEquals('Forbidden',$response->getReasonPhrase());
    }

    public function testCreateFromResponseString()
    {
        $responseString = <<<EOT
HTTP/1.1 200 OK
Server: Ant-Framework
Content-Type: text/html;charset=utf-8
Connection: keep-alive
Cache-Control: private
Expires: Fri, 23 Dec 2016 17:45:55 GMT
Content-Encoding: gzip
Set-Cookie: test=demo; path=/demo
Set-Cookie: foo=bar; expires=Fri, 23-Dec-16 17:46:00 GMT; domain=www.foobar.com; path=/test


EOT;
        $response = Response::createFromResponseStr($responseString);
        $this->assertEquals(200,$response->getStatusCode());
        $this->assertEquals('OK',$response->getReasonPhrase());
        $this->assertEquals('1.1',$response->getProtocolVersion());

        $this->assertEquals('gzip',$response->getHeaderLine('content-encoding'));
        $this->assertEquals('text/html;charset=utf-8',$response->getHeaderLine('Content-Type'));
        $this->assertEquals('keep-alive',$response->getHeaderLine('Connection'));
        $this->assertEquals('private',$response->getHeaderLine('Cache-Control'));
        $this->assertEquals('Fri, 23 Dec 2016 17:45:55 GMT',$response->getHeaderLine('Expires'));
        $this->assertEquals('Ant-Framework',$response->getHeaderLine('Server'));

        $cookies = $response->getCookies();
        // 生成唯一cookie
        $this->assertEquals(['test@:/demo','foo@www.foobar.com:/test'],array_keys($cookies));
        $cookieData = [
            [
                'name' => 'test',
                'value' => 'demo',
                'expires' => 0,
                'path' => '/demo',
                'domain' => '',
                'hostonly' => NULL,
                'secure' => false,
                'httponly' => false,
            ],
            [
                'name' => 'foo',
                'value' => 'bar',
                'expires' => 'Fri, 23-Dec-16 17:46:00 GMT',
                'path' => '/test',
                'domain' => 'www.foobar.com',
                'hostonly' => NULL,
                'secure' => false,
                'httponly' => false,
            ]
        ];

        // 对比cookie数据
        $this->assertEquals($cookieData,array_values($cookies));
    }

    public function testRedirect()
    {
        $response = (new Response())->redirect('http://foobar.com');
        $this->assertEquals(303,$response->getStatusCode());
        $this->assertEquals('http://foobar.com',$response->getHeaderLine('Location'));
    }


    /**
     * 测试Body装饰器
     */
    public function testHttpBodyRenderer()
    {
        $response = new Response();
        $response->keepImmutability(false);

        // 设置格式为Json
        $response->setType('json')
            ->setContent(['foo' => 'bar'])
            ->decorate();

        $this->assertEquals('{"foo":"bar"}',$response->getBody()->__toString());
        $this->assertEquals('application/json; charset=utf-8',$response->getHeaderLine('content-type'));

        // 设置格式为jsonp
        $response->withBody(new Stream(fopen('php://temp','w+')))
            ->setType('jsonp')
            ->setContent(['foo' => 'bar'])
            ->decorate();

        $this->assertEquals('callback({"foo":"bar"});',$response->getBody()->__toString());
        $this->assertEquals('application/javascript; charset=utf-8',$response->getHeaderLine('content-type'));

        // 设置格式为xml
        $response->withBody(new Stream(fopen('php://temp','w+')))
            ->setType('xml')
            ->setContent(['foo' => 'bar'])
            ->decorate();

        $backup = libxml_disable_entity_loader(true);
        $result = simplexml_load_string($response->getBody()->__toString());
        libxml_disable_entity_loader($backup);

        $this->assertEquals('bar',$result->foo);
        $this->assertEquals('text/xml; charset=utf-8',$response->getHeaderLine('content-type'));

        // 测试格式为file
        $response->withBody(new Stream(fopen('php://temp','w+')))
            ->setType('file')
            ->setContent("Test")
            ->decorate();

        $this->assertEquals('Test',$response->getBody()->__toString());
        $this->assertEquals('application/octet-stream',$response->getHeaderLine('content-type'));
        $this->assertEquals('attachment; filename="example.txt"',$response->getHeaderLine('content-disposition'));
        $this->assertEquals('binary',$response->getHeaderLine('Content-Transfer-Encoding'));
    }
}