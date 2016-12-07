<?php
namespace Ant\Http\Test;

use Ant\Http\Stream;

class StreamTest extends \PHPUnit_Framework_TestCase
{
    public function testStream()
    {
        $stream = new Stream(fopen('php://temp','r+'));

        $stream->write('foobar');

        //��Ϊ�򿪵�����,����û�н�β
        $this->assertFalse($stream->eof());
        $this->assertEquals(6,$stream->tell());
        $this->assertEquals(6,$stream->getSize());
        $this->assertTrue($stream->isSeekable());
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertEquals('foobar',$stream->__toString());
    }

    /**
     * ���Զ�дģʽ
     */
    public function testReadAndWriteMode()
    {
        $stream = new Stream(fopen('php://temp','r+'));

        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isReadable());

        //==================== ���Էָ��� ======================//
        $stream = new Stream(fopen('php://temp','r'));

        $this->assertTrue($stream->isReadable());
        $this->assertFalse($stream->isWritable());
    }

    /**
     *
     */
    public function testReadStream()
    {
        $stream = new Stream(fopen(__DIR__.DIRECTORY_SEPARATOR.'Test_Stream.txt','r'));

        $this->assertEquals('f',$stream->read(1));
        $this->assertEquals('oo',$stream->read(2));
        $this->assertEquals('bar',$stream->read(3));

        $stream->rewind();

        $this->assertEquals('foo',$stream->read(3));
        $this->assertEquals('bar',$stream->read(3));

        $stream->rewind();

        $this->assertEquals('f',$stream->read(1));
        $this->assertEquals('oobar',$stream->getContents());

        try{
            $stream = new Stream(fopen('php://temp','w'));
            $stream->write('foobar');
            $stream->read(6);
        }catch(\RuntimeException $e){
            $this->assertInstanceOf(\RuntimeException::class,$e);
        }
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testWriteStream()
    {
        $stream = new Stream(fopen('php://temp','w'));

        $stream->write('foobar');

        $this->assertEquals('',$stream->getContents());

        $stream = new Stream(fopen('php://temp','r'));
        $stream->write('foobar');
    }

}