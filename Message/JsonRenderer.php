<?php
namespace Ant\Http\Message;

class JsonRenderer extends Renderer
{
    public $type = 'application/json';

    public function decorate()
    {
        $this->httpMessage->getBody()->write($this->toJson());
        return $this->httpMessage->withHeader('Content-Type', $this->getType());
    }

    /**
     * @return string
     */
    public function toJson()
    {
        $output = json_encode($this->package);

        if ($output === false && json_last_error() !== JSON_ERROR_NONE) {
            throw new \UnexpectedValueException(json_last_error_msg(), json_last_error());
        }

        return $output;
    }
}