<?php
namespace Ant\Http\Message;

/**
 * Class JsonpRenderer
 * @package Ant\Http\Response\Renderer
 */
class JsonpRenderer extends JsonRenderer
{
    public $type = 'application/javascript';

    /**
     * @var string
     */
    public $getName = 'callback';

    /**
     * @var string
     */
    public $callName = 'callback';

    public function decorate()
    {
        $callName = isset($_GET[$this->getName]) ? $_GET[$this->getName] : $this->callName;

        $this->httpMessage->getBody()->write(
            "{$callName}({$this->toJson()});"
        );

        return $this->httpMessage->withHeader('Content-Type', $this->getType());
    }
}