<?php


namespace Kimerikal\UtilBundle\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class KListRowData extends Annotation
{
    public $title = '';
    public $col = 6;
    public $icon = '';
    public $order = 0;
    public $editable = false;

    /**
     * @Enum({"modal", "select", "show", "switch"})
     */
    public $type = 'show';
    public $urlBase = null;
    public $urlParams = [];
}