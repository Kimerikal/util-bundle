<?php

namespace Kimerikal\UtilBundle\Entity;

class TimeUtil {

    /**
     * Pasa una fecha en formato MySQL a formato Español.
     * 
     * @param String $fecha
     * @return String -- La fecha en español.
     */
    public static function fechaSpanish($fecha) {
        return $fechaES;
    }

    /**
     * Pasa una fecha en formato español a formato Ingles o MySQL.
     *
     * @param String $fecha
     * @return String -- La fecha en inglés.
     */
    public static function fechaInglesa() {
        return $fechaEN;
    }

    public static function fromMySQLToLocal($dateStr, $toFormat = 'd-m-Y') {
        $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
        if (!$date)
            return "";

        return $date->format($toFormat);
    }

    public static function fromLocalToMySQL($dateStr, $fromFormat = 'd-m-Y') {
        $date = \DateTime::createFromFormat($fromFormat, $dateStr);
        if (!$date)
            return "";

        return $date->format('Y-m-d H:i:s');
    }

    public static function fromStrToDate($dateStr, $fromFormat = 'd-m-Y') {
        return \DateTime::createFromFormat($fromFormat, $dateStr);
    }

    public static function humanTiming($timeStr, $in = '') {
        $time = \strtotime($timeStr);
        $time = \time() - $time;
        if (!self::isPast($timeStr, 'Y-m-d'))
            $time = \abs($time);

        $plurals = array('mes' => 'meses');
        $tokens = array(
            31536000 => 'año',
            2592000 => 'mes',
            604800 => 'semana',
            86400 => 'día',
            3600 => 'hora',
            60 => 'minuto',
            1 => 'segundo'
        );

        $units = array(
            'y' => 31536000,
            'm' => 2592000,
            'sem' => 604800,
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
            's' => 1
        );

        if (!empty($in) && \array_key_exists($in, $units)) {
            if ($time < $units[$in])
                return 0;

            return \floor($time / $units[$in]);
        } else {
            foreach ($tokens as $unit => $text) {
                if ($time < $unit)
                    continue;
                $numberOfUnits = floor($time / $unit);
                if ($numberOfUnits > 1) {
                    if (array_key_exists($text, $plurals)) {
                        $text = $plurals[$text];
                    } else {
                        $text .= 's';
                    }
                }

                return "Hace " . $numberOfUnits . ' ' . $text;
            }
        }
    }

    /**
     * Check if a given date is in the past.
     * 
     * @param type $dateStr - Date in a string or DateTime accepted
     * @param type $fromFormat - String with date format
     * @return boolean
     */
    public static function isPast($dateStr, $fromFormat = 'Y-m-d') {
        if (!is_string($dateStr))
            $dateStr = $dateStr->format($fromFormat);

        $date = \DateTime::createFromFormat($fromFormat, $dateStr);
        $today = new \DateTime("now");
        if ($date < $today)
            return true;

        return false;
    }

    public static function isToday($dateStr, $fromFormat = 'Y-m-d') {
        $date = null;
        if (is_string($dateStr))
            $date = \DateTime::createFromFormat($fromFormat, $dateStr);
        else 
            $date = $dateStr;
        
        $today = new \DateTime("now");
        if ($date->format('Y-m-d') == $today->format('Y-m-d'))
            return true;

        return false;
    }

    public static function mothStrToNumber($monthStr) {
        $moths = array(
            "enero" => "01",
            "febrero" => "02",
            "marzo" => "03",
            "abril" => "04",
            "mayo" => "05",
            "junio" => "06",
            "julio" => "07",
            "agosto" => "08",
            "septiembre" => "09",
            "octubre" => "10",
            "noviembre" => "11",
            "diciembre" => "12"
        );

        return $moths[\strtolower($monthStr)];
    }

    public static function mothNumberToStr($monthNum) {
        $moths = array(
            "01" => "Enero",
            "02" => "Febrero",
            "03" => "Marzo",
            "04" => "Abril",
            "05" => "Mayo",
            "06" => "Junio",
            "07" => "Julio",
            "08" => "Agosto",
            "09" => "Septiembre",
            "10" => "Octubre",
            "11" => "Noviembre",
            "12" => "Diciembre"
        );

        return $moths[$monthNum];
    }

}
