<?php
namespace Test;

use Ant\Http\Stream;

class StreamTest extends \PHPUnit_Framework_TestCase
{
    /**
     * php常用IO流读写测试
     */
    public function testReadAndWriteMode()
    {
        //==================== temp流必然支持读 ======================//
        $stream = new Stream(fopen('php://temp','r+'));
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isReadable());

        $stream = new Stream(fopen('php://temp','r'));
        $this->assertTrue($stream->isReadable());
        $this->assertFalse($stream->isWritable());

        //==================== input流为只可读 ======================//
        $stream = new Stream(fopen('php://input','r'));
        $this->assertTrue($stream->isReadable());
        $this->assertFalse($stream->isWritable());

        $stream = new Stream(fopen('php://input','w+'));
        $this->assertFalse($stream->isWritable());
        $this->assertTrue($stream->isReadable());

        //==================== output流为只可写 ======================//
        $stream = new Stream(fopen('php://output','w'));
        $this->assertFalse($stream->isReadable());
        $this->assertTrue($stream->isWritable());

        $stream = new Stream(fopen('php://output','r+'));
        $this->assertFalse($stream->isReadable());
        $this->assertTrue($stream->isWritable());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testReadStream()
    {
        $filename = './fixtures/Test_Stream.txt';
        $stream = new Stream(fopen($filename, 'r'));

        $this->assertEquals('f',$stream->read(1));
        $this->assertEquals('oo',$stream->read(2));
        $this->assertEquals('bar',$stream->read(3));

        $stream->rewind();
        $this->assertEquals('foo',$stream->read(3));
        $this->assertEquals('bar',$stream->read(3));

        $stream->rewind();
        $this->assertEquals('f',$stream->read(1));
        $this->assertEquals('oobar',$stream->getContents());
        $this->assertEquals('foobar',$stream->__toString());

        //在只写模式进行读取
        $stream = new Stream(fopen($filename,'w'));
        $stream->write('foobar');
        if(!$stream->isReadable()){
            $stream->read(6);
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

        $stream->rewind();
        $this->assertEquals('foobar',$stream->getContents());

        try{
            //输入错误参数
            $stream->write(['foo' => 'bar']);
        }catch(\InvalidArgumentException $e){
            $this->assertInstanceOf(\InvalidArgumentException::class,$e);
        }

        //在只读模式进行写入
        $stream = new Stream(fopen('php://input','r'));
        if(!$stream->isWritable()){
            $stream->write('foobar');
        }
    }

    /**
     * 尝试在流中进行定位
     * @expectedException \RuntimeException
     */
    public function testStreamSeek()
    {
        $stream = new Stream(fopen('php://temp','r+'));

        $this->assertTrue($stream->isSeekable());

        //定位
        $stream->write('foobar');
        $stream->seek(3);
        $this->assertEquals(3,$stream->tell());
        $this->assertEquals('bar',$stream->getContents());

        $stream = new Stream(stream_socket_server('tcp://0.0.0.0:12345'));

        $this->assertFalse($stream->isSeekable());

        try{
            //尝试重置流
            $stream->rewind();
        }catch(\InvalidArgumentException $e){
            $this->assertInstanceOf(\RuntimeException::class,$e);
        }

        //无法定位时强行定位抛出异常
        $stream->seek(1);
    }

    /**
     * 测试基本功能
     */
    public function testStream()
    {
        $stream = new Stream(fopen('php://temp','r+'));
        $stream->write('foobar');

        $this->assertFalse($stream->eof());
        $this->assertEquals(6, $stream->getSize());
        $this->assertTrue(is_resource($stream->detach()));
    }

    /**
     * 获取流的元数据
     */
    public function testStreamMetadata()
    {
        $stream = new Stream(fopen('php://temp','r'));

        $metadata = $stream->getMetadata();

        $this->assertEquals('PHP',$metadata['wrapper_type']);
        $this->assertEquals('TEMP',$metadata['stream_type']);
        $this->assertEquals('rb',$metadata['mode']);
        $this->assertEquals('php://temp',$metadata['uri']);
        $this->assertTrue($metadata['seekable']);
    }
}