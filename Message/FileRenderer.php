<?php
namespace Ant\Http\Message;

class FileRenderer extends Renderer
{
    public $type = 'application/octet-stream';

    public $fileName = 'example.txt';

    public function decorate()
    {
        $headers = [
            'Content-Type' => $this->type,
            "Content-Disposition" => "attachment; filename=\"{$this->fileName}\"",
            'Content-Transfer-Encoding' => 'binary',
        ];

        $http = $this->httpMessage;
        foreach($headers as $name => $value){
            $http = $this->httpMessage->withHeader($name,$value);
        }

        if(!is_string($this->package) && !is_integer($this->package)){
            throw new \RuntimeException('Response content must be string');
        }

        $http->getBody()->write($this->package);

        return $http;
    }
}