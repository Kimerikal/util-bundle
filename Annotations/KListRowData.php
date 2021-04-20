<?php


namespace Kimerikal\UtilBundle\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"PROPERTY","METHOD"})
 */
final class KListRowData extends Annotation
{
    /**
     * @Enum({"modal", "select", "show", "switch", "badge"})
     */
    public $type = 'show';
    public $title = '';
    public $col = 6;
    public $icon = '';
    public $order = 0;
    public $editable = false;
    public $urlBase = null;
    public $urlParams = [];
    public $prefix = '';
    public $suffix = '';
    public $functionParams = [];
    /**
     * @Enum({"primary", "secondary", "success", "danger", "warning", "info", "light", "dark"})
     */
    public $badgeColorClass = 'primary';
}