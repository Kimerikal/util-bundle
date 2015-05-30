<?php

namespace Kimerikal\UtilBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class UtilController extends Controller {

    const DEFAULT_MAIL = 'mailer_user';

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
        if (empty($clear)) {
            if (!is_array($clear))
                $clear = array($clear);

            foreach ($clear as $r)
                $url = str_replace($r, '', $url);
        }

        return $url;
    }

}
