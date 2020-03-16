<?php


namespace Kimerikal\UtilBundle\Model;


abstract class KListTPL
{
    protected $list;
    protected $currentPage;
    protected $icon;
    protected $pageTitle;
    protected $notFound;
    protected $image;
    protected $rowMainRoute;
    protected $rowMainRouteKey;
    protected $rowMainRouteMethod;
    protected $rowTitle;
    protected $breadcrumbs;
    protected $rowOptions;
    protected $rowData;
    protected $pagination;
    protected $filtersHtml;
    protected $modalsHtml;
    protected $batchActionsHtml;
    protected $customJS;
    protected $customCSS;
    protected $multiOnChangeURL;
    protected $ajaxSearchURL;
    protected $orderListHtml;
    protected $ajaxListSearchURL;

    public function __construct()
    {
        $this->icon = 'icon-drop';
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function setIcon($icon)
    {
        $this->icon = $icon;
    }
}