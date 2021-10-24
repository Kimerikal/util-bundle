<?php

namespace Kimerikal\UtilBundle\Entity;

class PushNotification
{
    /** @var string */
    private $title;
    /** @var string */
    private $content;
    /** @var array */
    private $extraData;

    public function __construct(string $title, string $content, array $extraData = [])
    {
        $this->title = $title;
        $this->content = $content;
        $this->extraData = $extraData;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return array
     */
    public function getExtraData(): array
    {
        return $this->extraData;
    }

    /**
     * @param array $extraData
     */
    public function setExtraData(array $extraData): void
    {
        $this->extraData = $extraData;
    }
}