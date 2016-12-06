<?php
namespace Ant\Http\Test;

use Ant\Http\Uri;

class UriTest extends \PHPUnit_Framework_TestCase
{
    public function testUri()
    {
        $uri = new Uri('https://www.example.com/base/?foo=bar#test');

        $this->assertEquals("test",$uri->getFragment());
        $this->assertEquals('foo=bar',$uri->getQuery());
    }
}