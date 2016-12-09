<?php
namespace Ant\Http\Test;

use Ant\Http\Uri;

class UriTest extends \PHPUnit_Framework_TestCase
{
    public function testUri()
    {
        $uri = new Uri('https://foo:bar@www.example.com:1234/base?foo=bar#test');

        $this->assertEquals('https',$uri->getScheme());
        $this->assertEquals('foo:bar',$uri->getUserInfo());
        $this->assertEquals('www.example.com',$uri->getHost());
        $this->assertEquals(1234,$uri->getPort());
        $this->assertEquals('foo:bar@www.example.com:1234',$uri->getAuthority());
        $this->assertEquals('/base',$uri->getPath());
        $this->assertEquals('foo=bar',$uri->getQuery());
        $this->assertEquals("test",$uri->getFragment());
        $this->assertEquals('https://foo:bar@www.example.com:1234/base?foo=bar#test',$uri->__toString());


        $uri = (new Uri(''))->withScheme('https')
            ->withUserInfo('foo','bar')
            ->withHost('www.example.com')
            ->withPort(1234)
            ->withPath('base')
            ->withQuery('foo=bar')
            ->withFragment('test');

        $this->assertEquals('https',$uri->getScheme());
        $this->assertEquals('foo:bar',$uri->getUserInfo());
        $this->assertEquals('www.example.com',$uri->getHost());
        $this->assertEquals(1234,$uri->getPort());
        $this->assertEquals('foo:bar@www.example.com:1234',$uri->getAuthority());
        $this->assertEquals('/base',$uri->getPath());
        $this->assertEquals('foo=bar',$uri->getQuery());
        $this->assertEquals("test",$uri->getFragment());
        $this->assertEquals('https://foo:bar@www.example.com:1234/base?foo=bar#test',$uri->__toString());
    }
}