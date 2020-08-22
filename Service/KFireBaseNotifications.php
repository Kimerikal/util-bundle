<?php

namespace Kimerikal\UtilBundle\Service;

use Doctrine\ORM\EntityManager;
use Kimerikal\UtilBundle\Model\BrowserPushReportable;

class KFireBaseNotifications
{
    /** @var string */
    private $fbServerKey;
    /** @var EntityManager */
    private $entityMananger;

    public function __construct(string $fbServerKey, EntityManager $entityManager)
    {
        $this->fbServerKey = $fbServerKey;
        $this->entityMananger = $entityManager;
    }

    public function notify(array $data = [], array $devices)
    {
        foreach ($devices as $device) {
            $result = $this->send([$device->getToken()], $data);
            if (!empty($result) && $result['failure'] == 1) {
                $this->entityMananger->remove($device);
                $this->entityMananger->flush();
            }
        }
    }

    /**
     * @param array $tokens
     * @param array $data
     * @return mixed
     */
    public function send(array $tokens, array $data = [])
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $fields = json_encode(['registration_ids' => $tokens, 'data' => $data]);
        $headers = ['Authorization: key=' . $this->fbServerKey, 'Content-Type: application/json'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, \JSON_OBJECT_AS_ARRAY);
    }
}