<?php
namespace Ant\Http\Message;

class HtmlRenderer extends Renderer
{
    public function decorate()
    {
        if(!is_string($this->package) && !is_integer($this->package)){
            throw new \RuntimeException('Response content must be string');
        }

        $this->httpMessage->getBody()->write($this->package);

        return $this->httpMessage->withHeader('Content-Type', $this->getType());
    }
}