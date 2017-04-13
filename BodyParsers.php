<?php
namespace Ant\Http;

class BodyParsers
{
    public static function create()
    {
        return [
            // 解析Xml数据,
            'text/xml'                          =>  [BodyParsers::class, "parseXml"],
            'application/xml'                   =>  [BodyParsers::class, "parseXml"],
            // 解析Json数据
            'text/json'                         =>  [BodyParsers::class, "parseJson"],
            'application/json'                  =>  [BodyParsers::class, "parseJson"],
            // 解析表单数据
            'multipart/form-data'               =>  [BodyParsers::class, "parseFormData"],
            // 解析Url encode格式
            'application/x-www-form-urlencoded' =>  [BodyParsers::class, "parseUrlEncode"],
        ];
    }

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

    /**
     * @param string $input
     * @return array|null
     */
    public static function parseFormData($input, ServerRequest $req)
    {
        if (!preg_match('/boundary="?(\S+)"?/', $req->getHeaderLine('content-type'), $match)) {
            return null;
        }

        $data = [];
        $uploadFiles = [];
        $bodyBoundary = '--' . $match[1] . "\r\n";
        // 将最后一行分界符剔除
        $body = substr(
            $input, 0,
            $req->getBody()->getSize() - (strlen($bodyBoundary) + 4)
        );

        foreach (explode($bodyBoundary, $body) as $buffer) {
            if ($buffer === '') {
                continue;
            }

            // 将Body头信息跟内容拆分
            list($header, $bufferBody) = explode("\r\n\r\n", $buffer, 2);
            $bufferBody = substr($bufferBody, 0, -2);
            $headerData = static::getDisposition($header);

            if (!$headerData) {
                continue;
            }

            if (preg_match('/name="(.*?)"$/', $headerData, $match)) {
                $data[$match[1]] = $bufferBody;

            } elseif (preg_match('/name="(.*?)"; filename="(.*?)"$/', $headerData, $match)) {
                $file = new Stream(fopen('php://temp','w'));
                $file->write($bufferBody);
                $file->rewind();

                $uploadFiles[$match[1]] = new UploadedFile([
                    'stream'    => $file,
                    'name'      => $match[1],
                    'size'      => $file->getSize()
                ]);
            }
        }

        return $data;
    }

    protected static function getDisposition($header)
    {
        foreach (explode("\r\n", $header) as $item) {
            list($headerName, $headerData) = explode(":", $item, 2);
            $headerName = trim(strtolower($headerName));
            if ($headerName == 'content-disposition') {
                return $headerData;
            }
        }

        return false;
    }
}