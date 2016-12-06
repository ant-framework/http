<?php
namespace Ant\Http\Test;

use Ant\Http\Message;
use Ant\Http\Request;
use Ant\Http\Response;
use Psr\Http\Message\MessageInterface as PsrMessage;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testMessageInstanceImplementedPsrMessage()
    {
        $request = new Request('GET','http://127.0.0.1');
        $response = new Response();

        $this->assertInstanceOf(PsrMessage::class,$request);
        $this->assertInstanceOf(PsrMessage::class,$response);
    }
}