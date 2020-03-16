<?php


namespace Kimerikal\UtilBundle\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"CLASS","ANNOTATION"})
 */
final class KListRowOption extends Annotation
{
    public $title = '';
    public $col = 6;
    public $icon = '';
    public $confirmation = false;
    public $route = '';
    public $routeKey = '';
    public $routeMethod = '';
    public $separator = false;
    public $ajax = false;
    public $aClass = '';
    public $routeAuto = null;
}