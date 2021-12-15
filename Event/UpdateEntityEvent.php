<?php

namespace Kimerikal\UtilBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class UpdateEntityEvent extends Event
{
    const NAME = 'k_util.events.update.entity';

    /** @var Object */
    protected $object;
    /** @var string */
    protected $entityMap;
    /** @var string */
    protected $entityClass;
    /** @var string */
    protected $updater;

    public function __construct($object, string $entityMap, string $entityClass, string $updater)
    {
        $this->object = $object;
        $this->entityMap = $entityMap;
        $this->entityClass = $entityClass;
        $this->updater = $updater;
    }

    /**
     * @return Object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @return string
     */
    public function getEntityMap(): string
    {
        return $this->entityMap;
    }

    /**
     * @return string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return string
     */
    public function getUpdater(): string
    {
        return $this->updater;
    }
}