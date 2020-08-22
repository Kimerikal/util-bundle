<?php

namespace Kimerikal\UtilBundle\Service;

use Doctrine\ORM\EntityManager;
use Kimerikal\UtilBundle\Model\BrowserPushReportable;
use Twig\Environment;

class KEmailNotifications
{
    /** @var Environment */
    private $twig;
    /** @var \Swift_Mailer */
    private $mailer;
    /** @var string */
    private $company;
    /** @var string */
    private $sender;

    public function __construct(Environment $twig, \Swift_Mailer $mailer, string $company, string $sender)
    {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->company = $company;
        $this->sender = $sender;
    }

    public function send($to, $title, $template, $params)
    {
        $body = $this->twig->render($template, $params);
        $message = \Swift_Message::newInstance()
            ->setSubject($title)
            ->setFrom([$this->sender => $this->company])
            ->setTo($to)
            ->setBody($body, 'text/html');

        return $this->mailer->send($message);
    }
}