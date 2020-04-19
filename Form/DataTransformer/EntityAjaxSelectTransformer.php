<?php

namespace Kimerikal\UtilBundle\Form\DataTransformer;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class EntityAjaxSelectTransformer implements DataTransformerInterface
{
    private $objectManager;
    private $className;

    public function __construct(ObjectManager $objectManager, $className)
    {
        $this->objectManager = $objectManager;
        $this->className = $className;
    }

    public function transform($obj)
    {
        if (empty($obj)) {
            return '';
        }

        return (string)$obj->getId();
    }

    public function reverseTransform($id)
    {
        if (!$id)
            return;

        if (is_object($id))
            return $id;

        $obj = $this->objectManager->getRepository($this->className)->find($id);
        if (null === $obj)
            throw new TransformationFailedException(sprintf('An object class: ' . $this->className . ' with id "%s" does not exist!', $id));

        return $obj;
    }
}