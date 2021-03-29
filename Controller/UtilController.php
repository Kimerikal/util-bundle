<?php

namespace Kimerikal\UtilBundle\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Kimerikal\EstablishmentBundle\Entity\Establishment;
use Kimerikal\UtilBundle\Annotations\KListRowData;
use Kimerikal\UtilBundle\Annotations\KTPLGeneric;
use Kimerikal\UtilBundle\Entity\ExceptionUtil;
use Kimerikal\UtilBundle\Form\SimpleForm;
use Kimerikal\UtilBundle\Repository\KPaginator;
use Kimerikal\UtilBundle\Repository\KRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Form;
use Kimerikal\UtilBundle\Entity\StrUtil;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Validator\Constraints\Date;

class UtilController extends Controller
{

    const DEFAULT_MAIL = 'mailer_user';
    const PUBLIC_ENV = 1;
    const ADMIN_ENV = 2;

    /**
     * @Route("/kadmin/autogen/ajax-search/{entity}-{page}", name="k_util_kadmin_autogen_search", methods={"GET","POST"}, defaults={"page": 1})
     * @return Response
     */
    public function searchAuto(Request $r, $entity, $page = 1)
    {
        KRepository::$CURRENT_USER = $this->getUser();
        $resp = ['done' => false, 'msg' => 'Ocurrió un error inesperado', 'html' => '', 'direct' => true];
        if (is_string($page))
            $page = intval(str_replace('pagina-', '', $page));

        if ($page < 1)
            $page = 1;

        $limit = 50;
        $offset = ($page - 1) * $limit;

        $classData = $this->getEntityUrlMap($entity);
        $entityInfo = $this->_em()->getClassMetadata($classData);
        $options = $this->getGenericAnnotations($entityInfo->getName(), $entity);

        try {
            $searchParams = $_REQUEST;
            $paginator = $this->_repo($classData)->loadAll($offset, $limit, false, $searchParams);
            $resp['mcount'] = $paginator->getTotal();
            $params = [
                'list' => $paginator->getList(),
                'limit' => $paginator->getLimit(),
                'total' => $paginator->getTotal(),
                'title' => '__toString',
                'mainRoute' => $options->rowMainRouteName,
                'mainRouteMethod' => $options->rowMainRouteMehod,
                'mainRouteKey' => $options->rowMainRouteKey,
                'image' => $options->imageMethod,
                'options' => $options->rowOptions,
                'data' => $this->annotationListData($entityInfo->getName()),
                'notFound' => 'No encontraron resultados para con los términos de búsqueda',
                'multiCheck' => true,
                'pagination' => $this->setPagination($r, $paginator->getTotal(), $page, $paginator->getLimit(), '/pagina-')
            ];

            $resp['mcount'] = $paginator->getTotal();
            $resp['html'] = $this->renderView('AdminBundle:Common:list-dyn-columns.html.twig', $params);
            $resp['done'] = true;
            $resp['msg'] = 'Ok';
        } catch (\Exception $e) {
            ExceptionUtil::logException($e, 'CustomerController::searchAction');
        }

        return new JsonResponse($resp);
    }


    /**
     * @Route("/kadmin/autogen/list/{entity}/{page}", name="k_util_kadmin_autogen_list", methods={"GET"}, defaults={"page": 1})
     * @param Request $r
     * @param $entity
     * @param int $page
     * @return Response
     * @throws \Exception
     */
    public function listAuto(Request $r, $entity, $page = 1)
    {
        KRepository::$CURRENT_USER = $this->getUser();
        $classData = $this->getEntityUrlMap($entity);
        $entityInfo = $this->_em()->getClassMetadata($classData);
        $options = $this->getGenericAnnotations($entityInfo->getName(), $entity);
        if (is_string($page) && $page != "1") {
            $page = intval(str_replace('pagina-', '', $page));
        }

        if ($page < 1)
            $page = 1;

        $limit = 50;
        $offset = $limit * ($page - 1);
        $paginator = $this->_repo($classData)->loadAll($offset, $limit, false, $_REQUEST);
        $breadcrumbs = [['name' => 'Lista de ' . $options->plural]];
        $actions = '';
        if (!empty($options->bulkActionsTemplate))
            $actions = $this->renderView($options->bulkActionsTemplate);

        // TODO: CUSTOM CSS, CUSTOM JS y MODALS
        //$modals = $this->renderView('KBlogBundle:Tiles:simple-list-modal.html.twig', array('interests' => $categories));
        //$js = 'bundles/kblog/js/list.js';
        $js = null;

        $pagination = null;
        $list = null;
        $totalCount = 0;
        if ($paginator instanceof Paginator) {
            $totalCount = $paginator->count();
            $pagination = $this->setPagination($r, $totalCount, $page, 50, '');
            $list = $paginator;
        } else if ($paginator instanceof KPaginator) {
            $totalCount = $paginator->getTotal();
            $pagination = $this->setPagination($r, $totalCount, $page, $paginator->getLimit(), '');
            $list = $paginator->getList();
        }

        return $this->renderSimpleList($list, '__toString', $options->rowMainRouteName, $options->rowMainRouteKey, $options->rowMainRouteMehod, $breadcrumbs, 'Lista de ' . $options->plural, 'list-' . $entity, $options->icon, 'No se encontraron ' . $options->plural, $options->imageMethod, $options->rowOptions, $this->annotationListData($entityInfo->getName()), $pagination, $options->listFiltersTemplate, ($options->newElementButton ? ['url' => $this->generateUrl('k_util_kadmin_autogen_edit', ['entity' => $entity]), 'name' => 'Crear ' . $options->name] : null), null, $actions, $js, '', $options->multiOnChangeRoute, $options->autoCompleteSearchRoute, $options->searchRoute, '', null, $options->rowMainRouteParams, $totalCount);
    }

    /**
     * @Route("/kadmin/autogen/edit/{entity}/{id}", name="k_util_kadmin_autogen_edit", methods={"POST","GET"}, defaults={"id": 0})
     * @return Response
     */
    public function editAuto(Request $r, $entity, $id = 0)
    {
        $object = null;
        $classData = $this->getEntityUrlMap($entity);
        $entityInfo = $this->_em()->getClassMetadata($classData);
        if (!empty($id))
            $object = $this->_repo($classData)->find($id);
        else {
            $objType = $entityInfo->getName();
            $object = new $objType;
        }

        $options = $this->getGenericAnnotations($entityInfo->getName(), $entity);

        $form = $this->createForm(new SimpleForm($entityInfo->getName(), $this->translator()), $object);
        $save = $this->checkSaveForm($r, $form);
        if ($save) {
            $this->addFlash('done', $options->name . ' guardado con éxito.');
            return $this->redirect($r->headers->get('referer'));
        } else if ($save === false) {
            $this->addFlash('error', 'Ocurrió un error inesperado.');
        }

        $breadcrumbs = [
            ['name' => 'Lista de ' . $options->plural, 'url' => $this->generateUrl('k_util_kadmin_autogen_list', ['entity' => $entity])],
            ['name' => 'Editar ' . $options->name]
        ];

        return $this->render('AdminBundle:Common:simple-form-page.html.twig', array('breadcrumbs' => $breadcrumbs, 'title' => !empty($object->getId()) ? 'Editar ' . $options->name . ': ' . $object->__toString() : 'Crear ' . $options->name, 'icon' => $options->icon, 'form' => $form->createView(), 'currentPage' => 'edit-' . $entity));
    }

    /**
     * @Route("/kadmin/autogen/remove-multi/{entity}", name="k_util_kadmin_autogen_remove_multi", methods={"POST","GET"}, defaults={"ajax"=1})
     * @param $entity
     * @param $id
     * @return Response
     * @throws \Exception
     */
    public function deleteMulti(Request $r, $entity)
    {
        $resp = ['done' => false, 'msg' => 'Ocurrió un error inesperado. Inténtelo de nuevo más tarde.'];
        $elements = $r->request->get('selelements');
        if (empty($elements))
            return new JsonResponse($resp);

        $classData = $this->getEntityUrlMap($entity);
        $entityInfo = $this->_em()->getClassMetadata($classData);
        $options = $this->getGenericAnnotations($entityInfo->getName(), $entity);
        $repo = $this->_repo($classData);
        foreach ($elements as $id) {
            $object = $repo->find($id);
            if ($object) {
                try {
                    $repo->delete($object);
                    $resp['done'] = true;
                    $resp['msg'] = $options->name . ' eliminado con éxito';
                } catch (\Exception $ex) {
                    ExceptionUtil::logException($ex, 'UtilController::delete');
                }
            }
        }

        return $this->redirect($r->headers->get('referer'));
    }

    /**
     * @Route("/kadmin/autogen/remove/{entity}/{id}/{ajax}", name="k_util_kadmin_autogen_remove", methods={"POST","GET"}, defaults={"ajax"=1})
     * @param $entity
     * @param $id
     * @return Response
     * @throws \Exception
     */
    public function delete(Request $r, $entity, $id, $ajax = 1)
    {
        $resp = ['done' => false, 'msg' => 'Ocurrió un error inesperado. Inténtelo de nuevo más tarde.'];
        $classData = $this->getEntityUrlMap($entity);
        $entityInfo = $this->_em()->getClassMetadata($classData);
        $options = $this->getGenericAnnotations($entityInfo->getName(), $entity);
        $repo = $this->_repo($classData);
        $object = $repo->find($id);
        if ($object) {
            try {
                $repo->delete($object);
                $resp['done'] = true;
                $resp['msg'] = $options->name . ' eliminado con éxito';
            } catch (\Exception $ex) {
                ExceptionUtil::logException($ex, 'UtilController::delete');
            }
        }

        if ($ajax == 1)
            return new JsonResponse($resp);

        return $this->redirect($r->headers->get('referer'));
    }

    public function getGenericAnnotations($entityClass, $entityName)
    {
        $reader = new AnnotationReader();
        $reflClass = new \ReflectionClass($entityClass);
        $options = $reader->getClassAnnotation($reflClass, KTPLGeneric::class);
        if (!$options)
            return null;

        if (empty($options->name))
            $options->name = $entityName;
        if (empty($options->plural))
            $options->plural = Inflector::pluralize($options->name);
        if (isset($options->rowOptions) && !empty($options->rowOptions))
            $options->rowOptions = $this->formatRowOptions($options->rowOptions);
        if (!empty($options->rowMainRouteAuto)) {
            $auto = explode(':', $options->rowMainRouteAuto);
            $options->rowMainRouteName = 'k_util_kadmin_autogen_' . $auto[0];
            $options->rowMainRouteParams = ['entity' => $auto[1], 'id' => 'id'];
        }
        if (!empty($options->listFiltersTemplate)) {
            $filterParams = ['entityName' => $entityName];
            if (!empty($options->listFiltersParams)) {
                foreach ($options->listFiltersParams as $key => $val) {
                    if (strpos($val, '|') !== false) {
                        $tmp = explode('|', $val);
                        $repo = $this->_repo($tmp[0]);
                        $method = $tmp[1];
                        if ($repo && method_exists($repo, $method))
                            $val = $repo->$method();
                        unset($tmp);
                    }
                    $filterParams[$key] = $val;
                }
            }

            $filterParams = array_merge($filterParams, $this->_repo($entityClass)->getListFilterParams('list'));
            $options->listFiltersTemplate = $this->renderView($options->listFiltersTemplate, $filterParams);
        } else
            $options->listFiltersTemplate = '';

        return $options;
    }

    protected function annotationListData($entityName)
    {
        $reader = new AnnotationReader();
        $reflectionClass = new \ReflectionClass($entityName);
        $props = $reflectionClass->getProperties();
        $rowData = [];
        foreach ($props as $p) {
            $reflectionProperty = new \ReflectionProperty($entityName, $p->name);
            $data = $reader->getPropertyAnnotation($reflectionProperty, KListRowData::class);
            if ($data)
                $rowData[] = ['method' => $p->name, 'col' => $data->col, 'title' => $data->title, 'icon' => $data->icon, 'order' => $data->order, 'editable' => $data->editable, 'urlBase' => $data->urlBase, 'urlParams' => $data->urlParams, 'type' => $data->type, 'suffix' => $data->suffix, 'functionParams' => [], 'badgeColorClass' => $data->badgeColorClass];
        }

        $methods = $reflectionClass->getMethods();
        foreach ($methods as $method) {
            $reflectionMethod = new \ReflectionMethod($entityName, $method->name);
            $data = $reader->getMethodAnnotation($reflectionMethod, KListRowData::class);
            if ($data) {
                $row = ['method' => $method->name, 'col' => $data->col, 'title' => $data->title, 'icon' => $data->icon, 'order' => $data->order, 'editable' => $data->editable, 'urlBase' => $data->urlBase, 'urlParams' => $data->urlParams, 'type' => $data->type, 'suffix' => $data->suffix, 'functionParams' => [], 'badgeColorClass' => $data->badgeColorClass];
                if (isset($data->functionParams) && count($data->functionParams) > 0) {
                    $values = [];
                    foreach ($data->functionParams as $param) {
                        switch ($param) {
                            case 'currentUser':
                                $values[] = !empty($this->getUser()) ? $this->getUser()->getId() : 0;
                                break;
                            default:
                                $values[] = $param;
                                break;
                        }
                        $row['functionParams'] = $values;
                    }
                }

                $rowData[] = $row;
            }
        }

        $this->sortByOrder($rowData);
        return $rowData;
    }

    private function sortByOrder(&$arr)
    {
        if (!$arr)
            return;

        usort($arr, function ($a, $b) {
            if ($a['order'] == $b['order'])
                return 0;
            return ($a['order'] < $b['order']) ? -1 : 1;
        });
    }

    private function formatRowOptions($options)
    {
        $rowOptions = [];
        foreach ($options as $option) {
            if (count($option->roles) > 0) {
                $continue = true;
                foreach ($option->roles as $role) {
                    if ($this->isGranted($role)) {
                        $continue = false;
                        break;
                    }
                }

                if ($continue)
                    continue;
            }
            $formatOption = ['aClass' => $option->aClass, 'icon' => $option->icon, 'name' => $option->title, 'type' => $option->type, 'roles' => $option->roles];
            if ($option->type == 'modal') {
                $class = $formatOption['aClass'] . ' ajaxFormLaunch';
                $formatOption['aClass'] = trim($class);
                $formatOption['ajax'] = true;
            }

            if (!empty($option->routeAuto)) {
                $auto = explode(':', $option->routeAuto);
                $formatOption['route'] = 'k_util_kadmin_autogen_' . $auto[0];
                $formatOption['routeParams'] = ['entity' => $auto[1], 'id' => 'id'];
            } else if (!empty($option->urlBase)) {
                $formatOption['route'] = $option->urlBase;
                if (!empty($option->urlParams) && count($option->urlParams) > 0) {
                    $formatOption['routeMethod'] = $option->routeMethod;
                    $formatOption['routeKey'] = $option->ur;
                }
            } else {
                $formatOption['route'] = $option->route;
                $formatOption['routeMethod'] = $option->routeMethod;
                $formatOption['routeKey'] = $option->routeKey;
            }

            if ($option->confirmation)
                $formatOption['confirmation'] = true;
            if ($option->ajax)
                $formatOption['ajax'] = true;
            if ($option->separator)
                $formatOption['separator'] = true;

            $rowOptions[] = $formatOption;
        }

        return $rowOptions;
    }

    protected function setPagination(Request $r, $totalCount, $currentPage, $pageLimit, $extraPageUrl = '')
    {
        if (!empty($currentPage) && !empty($totalCount) && !empty($pageLimit)
            && $totalCount > $pageLimit) {
            $firstResult = (($currentPage - 1) * $pageLimit) + 1;
            $lastResult = (($firstResult + $pageLimit) - 1);
            if ($lastResult > $totalCount)
                $lastResult = $totalCount;

            $base = str_replace($extraPageUrl . $currentPage, '', $r->getUri());
            if (!StrUtil::endsWith($base, '/'))
                $base .= '/';

            return array(
                'curPage' => $currentPage,
                'totalPages' => ceil($totalCount / $pageLimit),
                'baseUrl' => $base,
                'extraPageUrl' => $extraPageUrl,
                'sentence' => 'Mostrando del ' . $firstResult . ' al ' . $lastResult . ' de ' . $totalCount
            );
        }

        return null;
    }

    protected function renderSimpleList($list, $rowTitleMethod, $rowMainRoute, $rowMainRouteKey, $rowMainRouteMethod, $breadcrumbs = [], $pageTitle = 'Esto es una lista', $currentPage = '', $icon = 'fa fa-check', $notFound = 'No hay resultados que mostrar', $rowImage = '', $rowOptions = array(), $rowData = array(), $pagination = null, $filtersHtml = '', $newElement = null, $modalsHtml = '', $batchActionsHtml = '', $customJS = '', $customCSS = '', $multiOnChangeURL = '', $ajaxSearchURL = '', $ajaxListSearchURL = '', $orderListHtml = '', $mainRouteUrl = null, $rowMainRouteParams = [], $totalCount = 0)
    {
        $params = array(
            'list' => $list,
            'currentPage' => $currentPage,
            'icon' => $icon,
            'pageTitle' => $pageTitle,
            'notFound' => $notFound,
            'image' => $rowImage,
            'mainRouteUrl' => $mainRouteUrl,
            'rowMainRoute' => $rowMainRoute,
            'rowMainRouteKey' => $rowMainRouteKey,
            'rowMainRouteMethod' => $rowMainRouteMethod,
            'mainRouteParams' => $rowMainRouteParams,
            'rowTitle' => $rowTitleMethod,
            'breadcrumbs' => $breadcrumbs,
            'rowOptions' => $rowOptions,
            'rowData' => $rowData,
            'pagination' => $pagination,
            'filtersHtml' => $filtersHtml,
            'modalsHtml' => $modalsHtml,
            'batchActionsHtml' => $batchActionsHtml,
            'customJS' => $customJS,
            'customCSS' => $customCSS,
            'multiOnChangeURL' => $multiOnChangeURL,
            'ajaxSearchURL' => $ajaxSearchURL,
            'orderListHtml' => $orderListHtml,
            'ajaxListSearchURL' => $ajaxListSearchURL,
            'totalCount' => $totalCount
        );

        if ($newElement && count($newElement) == 2)
            $params['newElement'] = $newElement;

        return $this->render('AdminBundle:Common:simple-list-page.html.twig', $params);
    }

    /**
     * Maps an Entity with its url name defined en config.yml
     *
     * @param $entityClass
     * @return |null
     * @throws \Exception
     */
    protected function getEntityUrlMap($entityClass)
    {
        $map = $this->getParameter('entities_url_map');
        if (empty($map) || count($map) === 0)
            throw new \Exception('Bad request');

        $return = null;
        foreach ($map as $value) {
            if ($entityClass == $value['url']) {
                $return = $value['class'];
                break;
            }
        }

        if (!$return)
            throw new \Exception('Class not found');

        return $return;
    }

    /**
     * @return EntityManager
     */
    protected function _em()
    {
        return $this->getDoctrine()->getManager();
    }

    /**
     * @param $repo - Ex: AcmeBundle:EntityAcme
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function _repo($repo)
    {
        return $this->_em()->getRepository($repo);
    }

    /**
     * @return \Doctrine\Persistence\ObjectManager
     * @deprecated - use _em()
     */
    protected function doctrine()
    {
        return $this->_em();
    }

    /**
     * @param $repo
     * @return \Doctrine\Persistence\ObjectRepository
     * @deprecated
     */
    protected function doctrineRepo($repo)
    {
        return $this->_repo($repo);
    }

    protected function flashMsg($type, $msg)
    {
        $this->get('session')->getFlashBag()->add($type, $msg);
    }

    protected function mailTemplateSend($title, $content, $to, $subTitle = null)
    {
        $view = $this->renderView('AdminBundle:Mail:info-email.html.twig', array('title' => $title, 'subTitle' => $subTitle, 'content' => $content));
        return $this->mailing($title, array($this->parameter(self::DEFAULT_MAIL) => $this->parameter('app.title')), $to, $view);
    }

    protected function mailing($subject, $from, $to, $view)
    {
        $message = \Swift_Message::newInstance();
        $message->setSubject($subject)
            ->setFrom($from)
            ->setTo($to)
            ->setBody($view, 'text/html');

        return $this->get('mailer')->send($message);
    }

    protected function parameter($name)
    {
        return $this->container->getParameter($name);
    }

    protected function userGranted($role)
    {
        return $this->get('security.token_storage')->isGranted($role);
    }

    protected function getFullUrl(Request $request, $path = '', $clear = array('/app_dev.php'))
    {
        $base = $request->getUriForPath('');
        $gen = '';
        if (!empty($path))
            $gen = $this->generateUrl($path);

        $url = $base . $gen;
        if (!empty($clear)) {
            if (!is_array($clear))
                $clear = array($clear);

            foreach ($clear as $r)
                $url = str_replace($r, '', $url);
        }

        return $url;
    }

    /**
     *
     * @param type $key - if empty returns session object
     * @param type $value - if null and not empty $key removes var
     * @return type
     */
    protected function session($key = null, $value = null)
    {
        if (!empty($key) && !is_null($value)) {
            $this->get('session')->set($key, $value);
        } else if (!empty($key)) {
            $this->get('session')->remove($key);
        }

        return $this->get('session');
    }

    /**
     * Get global session var value if set, depending on parameters, compare
     * or check only if var is set as well.
     *
     * @param type $key - session variable key
     * @param type $default - default value to return
     * @param type $issetOnly - if true just check if variable is set
     * @param type $value - if not null compare session value with this value.
     * @return mixed
     */
    protected function checkSession($key = null, $default = false, $issetOnly = false, $value = null)
    {
        if (empty($key))
            return null;

        if (!$this->get('session')->has($key)) {
            return $default;
        } else if ($issetOnly) {
            return true;
        }

        if (!is_null($value)) {
            if ($this->get('session')->get($key) == $value)
                return true;
            else
                return false;
        }

        return $this->get('session')->get($key, $default);
    }

    protected function environment()
    {
        return $this->checkSession('user_environment', 'frontend');
    }

    protected function setEnvironment($environment = self::ADMIN_ENV)
    {
        return $this->session('user_environment', $environment);
    }

    protected function translator()
    {
        return $this->get('translator');
    }

    protected function translate($str)
    {
        if (empty($str))
            return '';

        return $this->translator()->trans($str);
    }

    protected function persist($object, $flush = true)
    {
        $this->doctrine()->persist($object);
        if ($flush)
            $this->doctrine()->flush();
    }

    /**
     *
     * @param Request $r
     * @param Form $form
     * @param boolean $save
     * @param array $callbackBefore - Array con el método a llamar key = method y un array de parámetros
     * @param array $callbackAfter
     * @return boolean
     */
    protected function checkSaveForm(Request $r, Form &$form, $save = true, $callbackBefore = null, $callbackAfter = null)
    {
        $form->handleRequest($r);
        if ($save && $form->isSubmitted() && $form->isValid()) {
            $obj = $form->getData();
            if (method_exists($obj, 'setUpdatedAt'))
                $obj->setUpdatedAt(new \DateTime());
            $this->callBackExec($form, $callbackBefore);
            $this->persist($obj);
            $this->callBackExec($form, $callbackAfter);

            return true;
        } else if ($form->isSubmitted() && !$form->isValid())
            return false;

        return null;
    }

    private function callBackExec($form, $callback)
    {
        if (\is_countable($callback) && \count($callback) >= 1 && \array_key_exists('method', $callback)) {
            $params = array();
            if (\array_key_exists('params', $callback)) {
                $params = $callback['params'];
                foreach ($params as &$p) {
                    if (is_string($p)) {
                        $tmp = \explode('|', $p);
                        if (count($tmp) > 1) {
                            $p = \call_user_func_array(array($form->getData(), $tmp[1]), $params);
                        }
                    }
                }
            }
            \call_user_func_array(array($form->getData(), $callback['method']), $params);
        }
    }

    protected function baseUrl()
    {
        return $this->getParameter('base_url');
    }

    /**
     * Method to launch a background process. All code below this call
     * will be executed on a different "thread".
     *
     * @param type $url --> Target user URL
     */
    public function processRedirect($url)
    {
        \header('Location: ' . $url);
        \ob_end_clean();
        \header("Connection: close");
        \ignore_user_abort(true);
        \ob_start();
        \header("Content-Length: 0");
        \ob_end_flush();
        \flush();
        \session_write_close();
    }

}
