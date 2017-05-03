<?php

namespace Kimerikal\UtilBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Form;
use Kimerikal\UtilBundle\Entity\StrUtil;

class UtilController extends Controller {

    const DEFAULT_MAIL = 'mailer_user';
    const PUBLIC_ENV = 1;
    const ADMIN_ENV = 2;

    protected function renderSimpleList($list, $rowTitleMethod, $rowMainRoute, $rowMainRouteKey, $rowMainRouteMethod, $breadcrumbs = array(), $pageTitle = 'Esto es una lista', $currentPage = '', $icon = 'fa fa-check', $notFound = 'No hay resultados que mostrar', $rowImage = '', $rowOptions = array(), $rowData = array()) {
        $params = array(
            'list' => $list,
            'currentPage' => $currentPage,
            'icon' => $icon,
            'pageTitle' => $pageTitle,
            'notFound' => $notFound,
            'image' => $rowImage,
            'rowMainRoute' => $rowMainRoute,
            'rowMainRouteKey' => $rowMainRouteKey,
            'rowMainRouteMethod' => $rowMainRouteMethod,
            'rowTitle' => $rowTitleMethod,
            'breadcrumbs' => $breadcrumbs,
            'rowOptions' => $rowOptions,
            'rowData' => $rowData
        );

        return $this->render('AdminBundle:Common:simple-list-page.html.twig', $params);
    }

    protected function doctrine() {
        return $this->getDoctrine()->getManager();
    }

    protected function doctrineRepo($repo) {
        return $this->getDoctrine()->getManager()->getRepository($repo);
    }

    protected function flashMsg($type, $msg) {
        $this->get('session')->getFlashBag()->add($type, $msg);
    }

    protected function mailing($subject, $from, $to, $view) {
        $message = \Swift_Message::newInstance();
        $message->setSubject($subject)
                ->setFrom($from)
                ->setTo($to)
                ->setBody($view, 'text/html');

        return $this->get('mailer')->send($message);
    }

    protected function parameter($name) {
        return $this->container->getParameter($name);
    }

    protected function userGranted($role) {
        return $this->get('security.context')->isGranted($role);
    }

    protected function getFullUrl(Request $request, $path = '', $clear = array('/app_dev.php')) {
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
    protected function session($key = null, $value = null) {
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
    protected function checkSession($key = null, $default = false, $issetOnly = false, $value = null) {
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

    protected function environment() {
        return $this->checkSession('user_environment', 'frontend');
    }

    protected function setEnvironment($environment = self::ADMIN_ENV) {
        return $this->session('user_environment', $environment);
    }

    protected function translator() {
        return $this->get('translator');
    }

    protected function translate($str) {
        if (empty($str))
            return '';

        return $this->translator()->trans($str);
    }

    protected function persist($object, $flush = true) {
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
    protected function checkSaveForm(Request $r, Form &$form, $save = true, $callbackBefore = null, $callbackAfter = null) {
        $form->handleRequest($r);
        if ($save && $form->isSubmitted() && $form->isValid()) {
            $obj = $form->getData();

            $this->callBackExec($form, $callbackBefore);
            if (\method_exists($obj, 'beforeSave')) {
                $obj->beforeSave();
            }

            $this->persist($obj);
            if (\method_exists($obj, 'afterSave')) {
                if ($obj->afterSave() == 2) {
                    $this->persist($obj);
                }
            }
            $this->callBackExec($form, $callbackAfter);

            return true;
        } else if ($form->isSubmitted() && !$form->isValid())
            return false;

        return null;
    }

    private function callBackExec($form, $callback) {
        if (\count($callback) >= 1 && \array_key_exists('method', $callback)) {
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

    protected function treeToJson($tree, $object = null, $openAllNodes = false, $hrefPattern = array(), $isSon = false) {
        $data = array();
        $addHref = \count($hrefPattern) > 0 && isset($hrefPattern['route']) && isset($hrefPattern['params']);
        foreach ($tree as $node) {
            $arr = array('id' => $node->getId(), 'text' => $node->getName());
            if ((!is_null($object) && $object->hasCategory($node->getId()))) {
                $arr['state'] = array('selected' => true);
            }

            if ($openAllNodes) {
                if (isset($arr['state']) && is_array($arr['state']))
                    $arr['state']['opened'] = true;
                else
                    $arr['state'] = array('opened' => true);
            }

            if ($addHref) {
                $params = array();
                foreach ($hrefPattern['params'] as $key => $method) {
                    $params[$key] = \call_user_func(array($node, $method));
                }

                $arr['a_attr'] = array('href' => $this->generateUrl($hrefPattern['route'], $params));
            }

            if (count($node->getChildren()) > 0) {
                $arr['children'] = $this->treeToJson($node->getChildren(), $object, $openAllNodes, $addHref, true);
            }

            $data[] = $arr;
        }

        if (!$isSon)
            return \json_encode($data);
        else
            return $data;
    }

    /**
     * Method to launch a background process. All code below this call 
     * will be executed on a different "thread".
     * 
     * @param type $url --> Target user URL
     */
    public function processRedirect($url) {
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
