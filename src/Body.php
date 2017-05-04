<?php
namespace Ant\Http;

/**
 * Class Body
 * @package Ant\Http
 */
class Body extends Stream
{
    /**
     * @param null $stream
     */
    public function __construct($stream = null)
    {
        if (is_null($stream)) {
            $stream = fopen('php://temp', 'w+');
        }

        parent::__construct($stream);
    }
}