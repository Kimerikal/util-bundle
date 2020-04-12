<?php


namespace Kimerikal\UtilBundle\Entity;

/**
 * Class MapsUtil
 * @package Kimerikal\UtilBundle\Entity
 * @deprecated use KGoogleMaps Service instead.
 */
class MapsUtil
{
    public static function getDataFromAddress(string $address, string $apiKey)
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $apiKey . '&components=country:ES';

        $tail = '&address=' . \urlencode($address);
        $data = \json_decode(\file_get_contents($url . $tail));
        if ($data->status == 'OK')
            return $data->results;

        return null;
    }

    public static function findLatLngFromAddress(string $address, string $apiKey, array $validTypes = ['establishment', 'food', 'restaurant', 'street_address'])
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $apiKey . '&components=country:ES';

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