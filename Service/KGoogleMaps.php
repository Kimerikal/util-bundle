<?php

namespace Kimerikal\UtilBundle\Service;

class KGoogleMaps
{
    public const KGM_MODE_DRIVING = 'driving';
    public const KGM_MODE_WALKING = 'walking';
    public const KGM_MODE_BICYCLING = 'bicycling';
    public const KGM_MODE_TRANSIT = 'transit';

    public const KGM_AVOID_TOLLS = 'tolls';
    public const KGM_AVOID_HIGHWAYS = 'highways';
    public const KGM_AVOID_FERRIES = 'ferries';
    public const KGM_AVOID_INDOOR = 'indoor';

    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param array $origins
     * @param array $destinations
     * @param array|null $waypoints
     * @param string $mode
     * @param string|null $avoid
     * @param string $lang
     * @return array|null
     */
    public function directions(array $origins, array $destinations, array $waypoints = null, string $mode = self::KGM_MODE_DRIVING, string $avoid = null, string $lang = 'es-ES')
    {
        $url = 'https://maps.googleapis.com/maps/api/directions/json?units=metric&mode=' . $mode . '&language=' . $lang . '&key=' . $this->apiKey;
        if (!empty($avoid))
            $url .= '&avoid=' . $avoid;

        $url .= '&origin=' . \urlencode(implode(',', $origins));
        $url .= '&destination=' . \urlencode(implode(',', $destinations));
        if (!empty($waypoints) && count($waypoints) > 0) {
            $url .= '&waypoints=' . \urlencode(implode('|', $waypoints));
        }
        $data = \json_decode(\file_get_contents($url));
        if ($data->status == 'OK')
            return $data->routes[0]->legs;

        return null;
    }

    public function getDataFromAddress(string $address, $countryCode = 'ES')
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $this->apiKey . '&components=country:' . $countryCode;

        $tail = '&address=' . \urlencode($address);
        $data = \json_decode(\file_get_contents($url . $tail));
        if ($data->status == 'OK')
            return $data->results;

        return null;
    }

    public function findLatLngFromAddress(string $address, array $validTypes = ['establishment', 'food', 'restaurant', 'street_address'], $countryCode = 'ES')
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $this->apiKey . '&components=country:' . $countryCode;

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