<?php


namespace Kimerikal\UtilBundle\Model;


interface PushNotificationDeviceRepository
{
    public function getTokens(int $offset = 0, int $limit = 500): ?array;
    public function removeByToken(array $tokensToRemove);
}