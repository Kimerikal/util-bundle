<?php


namespace Kimerikal\UtilBundle\Entity;


class MapsUtil
{
    public static function findLatLngFromAddress($formatedAddress, $localName = null)
    {
        $url = "https://maps.googleapis.com/maps/api/geocode/json?key=AIzaSyANB7av_2VybbhmiBeDQ2Om06J0wU59GTk&components=country:ES";
        /*$name = $client->getName();
        $formatedAddress = $client->getAddress()->formatAddress();*/
        $address = $formatedAddress;
        if (!empty($localName))
            $address = $localName .','.$formatedAddress;

        $tail = '&address=' . urlencode($address);
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
                    if ($type == 'establishment' || $type == 'food' || $type == 'restaurant')
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