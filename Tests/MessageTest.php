<?php
namespace Test;

use Ant\Http\Message;
use Ant\Http\Stream;
use Psr\Http\Message\StreamInterface as PsrStream;
use Psr\Http\Message\MessageInterface as PsrMessage;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testMessageInstanceImplementedPsrMessage()
    {
        $message = new HttpMessage();

        $this->assertInstanceOf(PsrMessage::class, $message);
    }

    /**
     * 测试Http不变性
     */
    public function testHttpMessageImmutability()
    {
        $message = new HttpMessage();

        $newMessage = $message->withHeader('foo','bar');
        $this->assertNotEquals($newMessage, $message);

        // Close immutability
        $newMessage = $message->keepImmutability(false)->withHeader('foo','bar');
        $this->assertEquals($newMessage, $message);
    }

    /**
     * 测试Http协议
     */
    public function testHttpMessageProtocolVersion()
    {
        $message = (new HttpMessage())->withProtocolVersion('1.0');

        $this->assertEquals('1.0',$message->getProtocolVersion());

        $this->assertEquals('1.1',$message->withProtocolVersion('1.1')->getProtocolVersion());

        try{
            (new HttpMessage())->withProtocolVersion([]);
        }catch (\Exception $e){
            $this->assertInstanceOf(\InvalidArgumentException::class,$e);
            $this->assertEquals('Input version error',$e->getMessage());
        }
    }

    /**
     * 测试Http头
     */
    public function testHttpMessageHeader()
    {
        $message = (new HttpMessage())->keepImmutability(false);

        // add header
        $message->withHeader('foo','bar,bat');
        $this->assertTrue($message->hasHeader('FoO'));
        $this->assertEquals('bar,bat',$message->getHeaderLine('FoO'));
        $this->assertEquals(['bar','bat'],$message->getHeader('Foo'));
        $this->assertEquals(['foo' => ['bar','bat']],$message->getHeaders());

        // add to append
        $message->withAddedHeader('FOO','bay');
        $this->assertEquals('bar,bat,bay',$message->getHeaderLine('FoO'));
        $this->assertEquals(['bar','bat','bay'],$message->getHeader('Foo'));
        $this->assertEquals(['foo' => ['bar','bat','bay']],$message->getHeaders());

        // output header
        $message->withHeader('fii',['baa','bae']);
        $this->assertEquals("Foo: bar,bat,bay\r\nFii: baa,bae\r\n",$message->headerToString());

        // remove header
        $message->withoutHeader('fOO')->withoutHeader('Fii');
        $this->assertFalse($message->hasHeader('foo'));
        $this->assertEquals('',$message->getHeaderLine('FoO'));
        $this->assertEquals([],$message->getHeader('Foo'));
        $this->assertEquals([],$message->getHeaders());
    }

    /**
     * 测试Body
     */
    public function testHttpMessageBody()
    {
        $message = (new HttpMessage())->keepImmutability(false);

        $message->withBody(new Stream(fopen('php://temp','w+')));
        $this->assertInstanceOf(PsrStream::class,$message->getBody());
    }
}

class HttpMessage extends Message
{
    public function __toString(){}
}