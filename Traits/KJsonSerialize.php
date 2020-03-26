<?php

namespace Kimerikal\UtilBundle\Traits;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Security\Acl\Util\ClassUtils;

Trait KJsonSerialize
{
    public function jsonSerialize()
    {
        $realClass = ClassUtils::getRealClass($this);
        $data = [];
        $reader = new AnnotationReader();
        $reflect = new \ReflectionClass($realClass);
        $props = $reflect->getProperties(\ReflectionProperty::IS_STATIC | \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);
        foreach ($props as $prop) {
            $hide = $reader->getPropertyAnnotation($prop, 'Kimerikal\\UtilBundle\\Annotations\\KJsonHide');
            if ($hide)
                continue;

            $formatMethod = 'jsonFormat' . ucfirst($prop->getName());
            $val = $this->{$prop->getName()};
            if (\method_exists($this, $formatMethod))
                $val = $this->$formatMethod();
            else if (is_object($val) && $val instanceof \JsonSerializable)
                $val = $this->{$prop->getName()}->jsonSerialize();

            $data[$prop->getName()] = $val;
        }

        return $data;
    }
}