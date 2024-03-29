<?php

namespace Kimerikal\UtilBundle\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class FormData extends Annotation {

    /**
     * @Enum({"text", "tree_select", "color", "number", "decimal", "email", "textarea", "ckeditor", "checkbox", "entity", "customForm", "simpleForm", "date", "imagecrop", "ajax_select", "entity_ajax_select", "choice", "enum", "file", "hidden", "quill"})
     */
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
    public $targetObject = '';
    public $mapped = true;
    public $jsonGroup = null;
    public $choiceLabel = null;
    public $class = null;
    public $format = null;
    public $inputType = null;
    public $groups = array();
    /**
     * @Enum({"no","newline","line"})
     */
    public $separator = 'no';
}
