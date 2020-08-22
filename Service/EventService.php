<?php

namespace Kimerikal\UtilBundle\Service;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;

class EventService
{
    /** @var EventDispatcher  */
    private $dispatcher;

    public function __construct(EventDispatcher $dispatcher)
    {
        error_log('CONST');
        $this->dispatcher = $dispatcher;
    }

    public function run(string $eventName, Event $event)
    {
        error_log('DURANTE');
        $this->dispatcher->dispatch($eventName, $event);
        error_log('DURANTE POST');
    }
}