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
        $url = $request->getUriForPath($path);
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
            if (\count($callbackBefore) >= 1 && \array_key_exists('method', $callbackBefore)) {
                $params = array();
                if (\array_key_exists('params', $callbackBefore)) {
                    $params = $callbackBefore['params'];
                    foreach ($params as &$p) {
                        $tmp = \explode('|', $p);
                        if (count($tmp) > 1) {
                            $p = \call_user_func_array(array($form->getData(), $tmp[1]), $params);
                        }
                    }
                }
                \call_user_func_array(array($form->getData(), $callbackBefore['method']), $params);
            }

            $this->persist($form->getData());

            if (\count($callbackAfter) >= 1 && \array_key_exists('method', $callbackAfter)) {
                \call_user_func_array(array($form->getData(), $callbackAfter['method']), (\array_key_exists('params', $callbackAfter) ? $callbackAfter['params'] : null));
            }

            return true;
        } else if ($form->isSubmitted() && !$form->isValid())
            return false;

        return null;
    }

}
