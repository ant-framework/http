<?php
namespace Ant\Http\Test;

use Ant\Http\Message;
use Ant\Http\Request;
use Ant\Http\Response;
use Ant\Http\Stream;
use Psr\Http\Message\StreamInterface as PsrStream;
use Psr\Http\Message\MessageInterface as PsrMessage;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testMessageInstanceImplementedPsrMessage()
    {

        $message = new HttpMessage();

        $this->assertInstanceOf(PsrMessage::class,$message);
    }

    /**
     * ²âÊÔHttp Message²»±äÐÔ
     */
    public function testHttpMessageImmutability()
    {
        $message = new HttpMessage();

        $newMessage = $message->withHeader('foo','bar');
        $this->assertNotEquals($newMessage,$message);

        // Close immutability
        $newMessage = $message->keepImmutability(false)->withHeader('foo','bar');
        $this->assertEquals($newMessage,$message);
    }

    public function testHttpMessageProtocolVersion()
    {
        $message = (new HttpMessage())->withProtocolVersion('1.0');

        $this->assertEquals('1.0',$message->getProtocolVersion());

        $this->assertEquals('1.1',$message->withProtocolVersion('1.1')->getProtocolVersion());
    }

    public function testHttpMessageHeader()
    {
        $message = (new HttpMessage())->keepImmutability(false);

        $message->withHeader('foo','bar,bat');
        $this->assertTrue($message->hasHeader('foo'));
        $this->assertEquals('bar,bat',$message->getHeaderLine('FoO'));
        $this->assertEquals(['bar','bat'],$message->getHeader('Foo'));
        $this->assertEquals(['foo' => ['bar','bat']],$message->getHeaders());

        $message->withAddedHeader('FOO','bay');
        $this->assertEquals('bar,bat,bay',$message->getHeaderLine('FoO'));
        $this->assertEquals(['bar','bat','bay'],$message->getHeader('Foo'));
        $this->assertEquals(['foo' => ['bar','bat','bay']],$message->getHeaders());

        $message->withHeader('fii',['baa','bae']);
        $this->assertEquals("Foo: bar,bat,bay\r\nFii: baa,bae\r\n",$message->headerToString());

        $message->withoutHeader('fOO')->withoutHeader('Fii');
        $this->assertFalse($message->hasHeader('foo'));
        $this->assertEquals('',$message->getHeaderLine('FoO'));
        $this->assertEquals([],$message->getHeader('Foo'));
        $this->assertEquals([],$message->getHeaders());
    }

    public function testHttpMessageBody()
    {
        $message = (new HttpMessage())->keepImmutability(false);

        $message->withBody(new Stream(fopen('php://temp','w+')));
        $this->assertInstanceOf(PsrStream::class,$message->getBody());
    }

    public function testHttpBodyRenderer()
    {
        $message = (new HttpMessage())->keepImmutability(false);

        //Ñ¡ÔñJsonäÖÈ¾Æ÷
        $message->withBody(new Stream(fopen('php://temp','w+')))
            ->selectRenderer('json')
            ->setPackage(['foo' => 'bar'])
            ->decorate();

        $this->assertEquals('{"foo":"bar"}',$message->getBody()->__toString());
        $this->assertEquals('application/json',$message->getHeaderLine('content-type'));

        //Ñ¡ÔñJsonpäÖÈ¾Æ÷
        $message->withBody(new Stream(fopen('php://temp','w+')))
            ->selectRenderer('js')
            ->setPackage(['foo' => 'bar'])
            ->decorate();

        $this->assertEquals('callback({"foo":"bar"});',$message->getBody()->__toString());
        $this->assertEquals('application/javascript',$message->getHeaderLine('content-type'));

        //Ñ¡ÔñXmläÖÈ¾Æ÷
        $message->withBody(new Stream(fopen('php://temp','w+')))
            ->selectRenderer('xml')
            ->setPackage(['foo' => 'bar'])
            ->decorate();

        $backup = libxml_disable_entity_loader(true);
        $result = simplexml_load_string($message->getBody()->__toString());
        libxml_disable_entity_loader($backup);

        $this->assertEquals('bar',$result->foo);
        $this->assertEquals('application/xml',$message->getHeaderLine('content-type'));

        //Ñ¡ÔñÎÄ¼þäÖÈ¾Æ÷
        $renderer = $message->withBody(new Stream(fopen('php://temp','w+')))
            ->selectRenderer('file')
            ->setPackage("Test");

        $renderer->fileName = 'foobar.txt';
        $renderer->decorate();

        $this->assertEquals('Test',$message->getBody()->__toString());
        $this->assertEquals('application/octet-stream',$message->getHeaderLine('content-type'));
        $this->assertEquals('attachment; filename="foobar.txt"',$message->getHeaderLine('content-disposition'));
        $this->assertEquals('binary',$message->getHeaderLine('Content-Transfer-Encoding'));
    }
}

class HttpMessage extends Message
{
    public function __toString(){}
}