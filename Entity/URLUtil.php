<?php

namespace Kimerikal\UtilBundle\Entity;

class URLUtil {

    const DEFAULT_AGENT = 'Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';

    public static function getUrl($url, $referer = 'http://www.google.com/', $user_agent = self::DEFAULT_AGENT) {
        header('Content-type: text/html; charset=UTF-8');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_COOKIEFILE, "cookie.txt");
        curl_setopt($ch, CURLOPT_COOKIEJAR, "cookie.txt");
        $body = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $httpcode, 'body' => $body];
    }

    public static function postUrl($url, $postParams, $referer = 'http://www.google.com/', $user_agent = self::DEFAULT_AGENT) {
        header('Content-type: text/html; charset=UTF-8');
        $handler = curl_init();
        curl_setopt($handler, CURLOPT_URL, $url);
        curl_setopt($handler, CURLOPT_POST, true);
        curl_setopt($handler, CURLOPT_POSTFIELDS, $postParams);
        curl_setopt($handler, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($handler, CURLOPT_HEADER, 0);
        curl_setopt($handler, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handler, CURLOPT_REFERER, $referer);
        curl_setopt($handler, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($handler, CURLOPT_TIMEOUT, 120);
        curl_setopt($handler, CURLOPT_MAXREDIRS, 10);
        curl_setopt($handler, CURLOPT_ENCODING, "");
        curl_setopt($handler, CURLOPT_COOKIEFILE, "cookie.txt");
        curl_setopt($handler, CURLOPT_COOKIEJAR, "cookie.txt");
        $body = curl_exec($handler);
        curl_close($handler);

        return $body;
    }

    public static function fetchUrl($url, $postParams, $headers = null, $method = 'post', $referer = 'http://www.google.com/', $user_agent = '')
    {
        $handler = curl_init();
        curl_setopt($handler, CURLOPT_URL, $url);
        if (empty($method) || strtolower($method) == 'post') {
            curl_setopt($handler, CURLOPT_POST, true);
            curl_setopt($handler, CURLOPT_POSTFIELDS, http_build_query($postParams));
        } else if (strtolower($method) == 'put') {
            curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($handler, CURLOPT_POSTFIELDS, $postParams);
        } else if (strtolower($method) == 'get')
            curl_setopt($handler, CURLOPT_HTTPGET, true);

        curl_setopt($handler, CURLOPT_USERAGENT, $user_agent);
        if ($headers && count($headers) > 0)
            curl_setopt($handler, CURLOPT_HTTPHEADER, $headers);
        else
            curl_setopt($handler, CURLOPT_HEADER, 0);
        curl_setopt($handler, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handler, CURLOPT_REFERER, $referer);
        curl_setopt($handler, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($handler, CURLOPT_TIMEOUT, 120);
        curl_setopt($handler, CURLOPT_MAXREDIRS, 10);
        curl_setopt($handler, CURLOPT_ENCODING, "");
        curl_setopt($handler, CURLOPT_COOKIEFILE, "cookie.txt");
        curl_setopt($handler, CURLOPT_COOKIEJAR, "cookie.txt");
        $body = curl_exec($handler);
        $httpcode = curl_getinfo($handler, CURLINFO_HTTP_CODE);

        $err = '';
        if (false === $body) {
            $err = curl_error($handler);
        }
        curl_close($handler);

        return $body;
    }

    public static function isHtml($string) {
        if ($string != strip_tags($string)) {
            return true; // Contains HTML
        }
        return false; // Does not contain HTML
    }

}
