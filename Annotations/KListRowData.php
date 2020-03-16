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
}