<?php

namespace Kimerikal\UtilBundle\Service;

class KGoogleMaps
{
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getDataFromAddress(string $address)
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $this->apiKey . '&components=country:ES';

        $tail = '&address=' . \urlencode($address);
        $data = \json_decode(\file_get_contents($url . $tail));
        if ($data->status == 'OK')
            return $data->results;

        return null;
    }

    public function findLatLngFromAddress(string $address, array $validTypes = ['establishment', 'food', 'restaurant', 'street_address'])
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $this->apiKey . '&components=country:ES';

        $tail = '&address=' . \urlencode($address);
        $data = \json_decode(\file_get_contents($url . $tail));
        if ($data->status == 'OK') {
            $results = $data->results;
            $position = new \stdClass();
            $position->lat = 0;
            $position->lng = 0;
            $position->matched = 0;
            for ($i = 0; $i < count($results); $i++) {
                $matched = 0;
                foreach ($results[$i]->types as $type) {
                    if (in_array($type, $validTypes))
                        $matched++;
                }

                if ($position->matched < $matched) {
                    $position->matched = $matched;
                    $position->lat = $results[$i]->geometry->location->lat;
                    $position->lng = $results[$i]->geometry->location->lng;
                }
            }

            if ($position->matched > 0)
                return $position;
        }

        return null;
    }
}