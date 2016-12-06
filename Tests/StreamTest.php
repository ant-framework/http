<?php
namespace Ant\Http\Test;

use Ant\Http\Stream;

class StreamTest extends \PHPUnit_Framework_TestCase
{
    public function testStream()
    {
        $stream = new Stream(fopen('php://temp','r+'));

        $stream->write('foobar');

        $this->assertEquals(6,$stream->tell());
        $this->assertEquals(6,$stream->getSize());
        $this->assertTrue($stream->isSeekable());
        $this->assertEquals('foobar',$stream->__toString());
    }
}