<?php

namespace Kimerikal\UtilBundle\Entity;

use Kimerikal\UtilBundle\Entity\URLUtil;

final class IpLocation {

    const API_KEY = '5586e89ed2df17f4691916decdecb021efec655649b70536516b4a4fa10fdfda';
    const PRECISION_CITY = 'ip-city';
    const PRECISION_COUNTRY = 'ip-country';
    const SERVICE = 'api.ipinfodb.com';
    const SERVICE_VERSION = 'v3';
    const FORMAT = 'json';

    protected $errors;
    protected $precion;
    protected $country;
    protected $city;

    public function __construct($host, $precision = self::PRECISION_CITY) {
        $this->host = $host;
        $this->precion = $precision;
        $this->errors = array();

        if ($precision == self::PRECISION_CITY)
            $this->getCity();
        else
            $this->getCountry();
    }

    public function getError() {
        return implode("\n", $this->errors);
    }

    public function getCountry() {
        if (empty($this->country))
            $this->country = $this->getResult(self::PRECISION_COUNTRY);

        return $this->country;
    }

    public function getCity() {
        if (empty($this->city))
            $this->city = $this->getResult(self::PRECISION_CITY);

        return $this->city;
    }

    private function getResult($precision) {
        $ip = \gethostbyname($this->host);
        $ip = "83.38.59.30";

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            try {
                $url = 'https://' . self::SERVICE . '/' . self::SERVICE_VERSION . '/' . $precision . '/?key=' . self::API_KEY . '&ip=' . $ip . '&format=' . self::FORMAT;
                $json = URLUtil::getUrl($url);

                if ($json)
                    return \json_decode($json, true);
            } catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
                return;
            }
        }

        $this->errors[] = '"' . $host . '" is not a valid IP address or hostname.';
        return;
    }

}
