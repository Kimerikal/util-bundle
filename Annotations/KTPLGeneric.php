<?php


namespace Kimerikal\UtilBundle\Annotations;

use Doctrine\ORM\Mapping\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
final class KTPLGeneric implements Annotation
{
    public $name;
    public $plural;
    public $icon = '';
    public $listJS = '';
    public $formJS = '';
    public $rowOptions = [];
}