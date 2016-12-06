<?php
namespace Ant\Http\Test;

use Ant\Http\Response;

class ResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testResponse()
    {
        $response = new Response();
        $this->assertEquals("HTTP/1.1 200 OK\r\n\r\n",$response->__toString());
    }
}