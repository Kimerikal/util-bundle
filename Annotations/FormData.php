<?php

namespace Kimerikal\UtilBundle\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class FormData extends Annotation {
    public $type;
    public $label;
    public $col = 12;
    public $placeholder = '';
    public $required = false;
    public $newLine = false;
    public $order = 0;
    public $className;
    public $customAttrs = array();
    public $choiceData = array();
    public $events = array();
    public $emptyValue = '';
    public $dataUrl = '';
    public $mapped = true;
    public $jsonGroup = null;
    public $choiceLabel = null;
    public $class = null;
}
