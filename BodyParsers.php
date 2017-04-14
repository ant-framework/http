<?php
namespace Ant\Http;

class BodyParsers
{
    /**
     * @param string $input
     * @return array|null
     */
    public static function parseJson($input)
    {
        $data = json_decode($input, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * @param string $input
     * @return \SimpleXMLElement
     */
    public static function parseXml($input)
    {
        $backup = libxml_disable_entity_loader(true);
        $data = simplexml_load_string($input);
        libxml_disable_entity_loader($backup);
        return $data;
    }

    /**
     * @param string $input
     * @return array|null
     */
    public static function parseUrlEncode($input)
    {
        parse_str($input, $data);
        return $data;
    }
}