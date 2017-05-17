<?php
namespace Test;


use Ant\Http\CliServerRequest;

class UploadedFileTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Psr\Http\Message\UploadedFileInterface
     */
    protected $file;

    public function setUp()
    {
        $filename = __DIR__ . "/fixtures/BodyIsForm.txt";

        if (file_exists($filename) && is_readable($filename)) {
            $request = CliServerRequest::createFromString(
                file_get_contents($filename)
            );

            $this->file = $request->getUploadedFiles()['file-test'];
        }
    }

    public function testFileToStream()
    {
        $this->assertInstanceOf(
            \Psr\Http\Message\StreamInterface::class,
            $this->file->getStream()
        );
    }

    public function testFileInfo()
    {
        $len = strlen($this->file->getStream()->__toString());
        $this->assertEquals($this->file->getSize(), $len);

        $this->assertEquals($this->file->getClientFilename(), 'file-test.txt');
    }

    // Todo ≤‚ ‘MoveTo
}