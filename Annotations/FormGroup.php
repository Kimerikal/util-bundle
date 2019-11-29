<?php


namespace Kimerikal\UtilBundle\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"ANNOTATION"})
 */
final class FormGroup extends Annotation
{
    public $type;
    public $label;
    public $col;
}
