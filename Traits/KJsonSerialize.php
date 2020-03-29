<?php

namespace Kimerikal\UtilBundle\Traits;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Security\Acl\Util\ClassUtils;

Trait KJsonSerialize
{
    public function jsonSerialize()
    {
        $data = [];
        $realClass = ClassUtils::getRealClass($this);
        $reader = new AnnotationReader();
        $reflect = new \ReflectionClass($realClass);
        $props = $reflect->getProperties(\ReflectionProperty::IS_STATIC | \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);
        foreach ($props as $prop) {
            if ($reader->getPropertyAnnotation($prop, 'Kimerikal\\UtilBundle\\Annotations\\KJsonHide'))
                continue;

            $formatMethod = 'jsonFormat' . ucfirst($prop->getName());
            $val = $this->{$prop->getName()};
            if (\method_exists($this, $formatMethod))
                $val = $this->$formatMethod();
            elseif ($val instanceof PersistentCollection)
                $val = $val->toArray();

            $data[$prop->getName()] = $val;
        }

        return $data;
    }
}