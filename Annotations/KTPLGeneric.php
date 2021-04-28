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
    public $imageMethod = '';
    public $rowMainRouteName = 'k_util_kadmin_autogen_edit';
    public $rowMainRouteParams = [];
    /** @deprecated - use rowMainRouteParams or rowMainRouteAuto */
    public $rowMainRouteKey = '';
    /** @deprecated - use rowMainRouteParams or rowMainRouteAuto */
    public $rowMainRouteMehod = '';
    public $rowMainRouteAuto;
    public $newElementButton = true;
    public $searchRoute = '';
    public $multiOnChangeRoute = '';
    public $autoCompleteSearchRoute = '';
    public $bulkActionsTemplate = '';
    public $bulkActionsParams = [];
    public $listFiltersTemplate = '';
    public $listFiltersParams = [];
}