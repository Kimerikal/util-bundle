<?php

namespace Kimerikal\UtilBundle\Service;

class KFireBaseNotifications
{
    private $fbServerKey;

    public function __construct($fbServerKey)
    {
        $this->fbServerKey = $fbServerKey;
    }

    /**
     * @param array $tokens
     * @param array $data
     * @return mixed
     */
    public function sendNotification(array $tokens, array $data = []) {
        $url = 'https://fcm.googleapis.com/fcm/send';
        /*[
            'webpush' => [
                'actions' => ['show_notification']
            ],
            'notification' => [
                'title' => $title,
                'body' => $message
            ]
        ]*/
        $fields = [
            'registration_ids' => $tokens,
            'data' => $data
        ];
        $fields = json_encode ( $fields );
        $headers = array (
            'Authorization: key=' . $this->fbServerKey,
            'Content-Type: application/json'
        );

        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_POST, true );
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );

        $result = curl_exec ( $ch );
        curl_close ( $ch );

        return json_decode($result, \JSON_OBJECT_AS_ARRAY);
    }
}