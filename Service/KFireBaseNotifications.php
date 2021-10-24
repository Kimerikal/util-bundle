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
        if (count($devices) === 0)
            return;

        foreach ($devices as $device) {
            if (!$device instanceof BrowserPushReportable)
                continue;

            $result = $this->send([$device->getToken()], $data);
            if (!empty($result) && isset($result['failure']) && $result['failure'] == 1) {
                $this->entityMananger->remove($device);
                $this->entityMananger->flush();
            }
        }
    }

    public function chuckedNotifyAll(string $repo, array $data)
    {
        $devices = null;
        $sent = 0;
        $total = 0;
        $limit = 500;
        $offset = 0;
        $repoObj = $this->entityMananger->getRepository($repo);

        do {
            $devices = $repoObj->getTokens($offset, $limit);
            if (!empty($devices)) {
                $total += count($devices);
                $response = $this->send($devices, $data);
                if ($response && array_key_exists('failure', $response) && $response['failure'] > 0) {
                    $tokensToRemove = [];
                    $i = 0;
                    foreach ($response['results'] as $result) {
                        if (isset($result['error']))
                            $tokensToRemove[] = $devices[$i];
                        else
                            $sent++;
                        $i++;
                    }

                    if (count($tokensToRemove) > 0)
                        $repoObj->removeByToken($tokensToRemove);
                } else
                    $sent += count($devices);
            } else
                break;

            $offset += $limit;
        } while ($devices);

        return ['sent' => $sent, 'total' => $total];
    }

    /**
     * @param array $tokens
     * @param array $data
     * @return mixed
     */
    public function send(array $tokens, array $data = [])
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $fields = json_encode(['registration_ids' => $tokens, 'notification' => $data]);
        $headers = ['Authorization: key=' . $this->fbServerKey, 'Content-Type: application/json'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, \JSON_OBJECT_AS_ARRAY);
    }
}