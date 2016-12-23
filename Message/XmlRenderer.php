<?php
namespace Ant\Http\Message;

class XmlRenderer extends  Renderer
{
    public $type = 'application/xml';

    public function decorate()
    {
        $this->httpMessage->getBody()->write($this->toXml());

        return $this->httpMessage->withHeader('Content-Type', $this->getType());
    }

    /**
     * 输出XML格式数据
     *
     * @return string
     */
    protected function toXml()
    {
        $sxe = new \SimpleXMLElement('<xml/>');
        $this->addChildToElement($sxe,$this->package);

        return $sxe->asXML();
    }

    /**
     * 添加子节点
     *
     * @param \SimpleXMLElement $element
     * @param array|object $data
     */
    protected function addChildToElement(\SimpleXMLElement $element, $data)
    {
        if(!$this->checkType($data)){
            $data = ['item' => $data];
        }

        foreach($data as $key => $val){
            if(!is_string($val) && !is_int($val)){
                $childElement = $element->addChild($key);
                $this->addChildToElement($childElement,$val);
            }else{
                $element->addChild($key,$val);
            }
        }
    }

    /**
     * 检查数据类型
     *
     * @return bool
     */
    protected function checkType($data)
    {
        if(is_array($data) || is_object($data)){
            return true;
        }

        return false;
    }
}