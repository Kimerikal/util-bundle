<?php


namespace Kimerikal\UtilBundle\Traits;

Trait KJsonSerialize
{
    public function jsonSerialize()
    {
        $reflect = new \ReflectionClass($this);
        $props = $reflect->getProperties(\ReflectionProperty::IS_STATIC | \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);
        $propsIterator = function () use ($props) {
            foreach ($props as $prop) {
                // TODO get annotations to check if it must not be shown
                $formatMethod = 'jsonFormat' . ucfirst($prop->getName());
                $val = $this->{$prop->getName()};
                if (\method_exists($this, $formatMethod))
                    $val = $this->$formatMethod();
                else if (is_object($val) && $val instanceof \JsonSerializable)
                    $val = $this->{$prop->getName()}->jsonSerialize();

                yield $prop->getName() => $val;
            }
        };

        return iterator_to_array($propsIterator());
    }
}