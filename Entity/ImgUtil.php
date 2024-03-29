<?php

namespace Kimerikal\UtilBundle\Entity;

class ImgUtil
{

    const THUMB_WIDTH = 256;

    public static function store($b64Img, $path, $filename = '')
    {
        try {
            $img = \str_replace('data:image/png;base64,', '', $b64Img);
            $img = \str_replace(' ', '+', $img);
            $data = base64_decode($img);
            $file = $path . DS . (empty($filename) ? \uniqid() . '.jpg' : $filename);
            $success = \file_put_contents($file, $data);

            if ($success)
                return $file;
        } catch (Exception $e) {

        }
        return false;
    }

    public static function fromFileToBase64($file)
    {
        $base = "";

        if (!empty($file) && file_exists($file)) {
            $data = file_get_contents($file);
            if ($data != null)
                $base = base64_encode($data);
        }

        return $base;
    }

    public static function dirThumbs($pathToImages, $pathToThumbs, $thumbWidth)
    {
        // Abrimos el directorio
        $dir = opendir($pathToImages);

        // Buscamos todos los jpg
        while (false !== ($fname = readdir($dir))) {
            $info = pathinfo($pathToImages . $fname);
            // Continuamos sÃ³lo si es jpg
            if (strtolower($info['extension']) == 'jpg') {

                // Cargamos la imagen y comprobamos el tamaÃ±o
                $img = imagecreatefromjpeg("{$pathToImages}{$fname}");
                $width = imagesx($img);
                $height = imagesy($img);

                // Calculamos el tamaÃ±o del thumb
                $new_width = $thumbWidth;
                $new_height = floor($height * ($thumbWidth / $width));

                // Creamos una img temporal
                $tmp_img = imagecreatetruecolor($new_width, $new_height);

                // Copiamos y redimensionamos la vieja img
                imagecopyresized($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

                // Guardamos
                imagejpeg($tmp_img, "{$pathToThumbs}{$fname}");
            }
        }
        // Cerramos el directorio
        closedir($dir);
    }

    public static function resizeFile($file, $finalWidth, $newPath = '')
    {
        if (empty($newPath))
            $newPath = $file;

        list($src_width, $src_height, $type) = getimagesize($file);


        if ($src_width < $finalWidth)
            $finalWidth = $src_width;

        // Cargamos la imagen y comprobamos el tamaÃƒÂ±o
        $png = false;
        $img = null;
        try {
            if (self::isImage($file, \IMAGETYPE_JPEG)) {
                $img = @imagecreatefromjpeg($file);
            } else if (self::isImage($file, \IMAGETYPE_PNG)) {
                $img = @imagecreatefrompng($file);
                $png = true;
            }
        } catch (\Exception $e) {
            ExceptionUtil::logException($e, 'ImgUtil::resizeFile');
        }

        if (!$img)
            return null;

        $width = imagesx($img);
        $height = imagesy($img);

        // Calculamos el tamaÃƒÂ±o del thumb
        $new_height = floor($height * ($finalWidth / $width));

        // Creamos una img temporal
        $tmp_img = imagecreatetruecolor($finalWidth, $new_height);

        // Copiamos y redimensionamos la vieja img
        imagecopyresized($tmp_img, $img, 0, 0, 0, 0, $finalWidth, $new_height, $width, $height);

        return self::write($type, $tmp_img, $newPath);
    }

    public static function isImage($path, $type = null)
    {
        $a = getimagesize($path);
        $imageType = $a[2];

        if (in_array($imageType, array(\IMAGETYPE_GIF, \IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_BMP))) {
            if (!empty($type)) {
                if ($imageType == $type)
                    return true;

                return false;
            }

            return true;
        }

        return false;
    }

    public static function redimensionarFromString($data)
    {
        $data = base64_decode($data);

        $im = imagecreatefromstring($data);
        if ($im !== false) {
            header('Content-Type: image/png');
            imagepng($im);
            imagedestroy($im);
        } else {
            echo 'An error occurred.';
        }
    }

    public static function resize($file, $newFile, $finalWidth, $finalHeight)
    {
        $png = false;

        // Cargamos la imagen y comprobamos el tamaÃƒÂ±o
        $image = imagecreatefromjpeg("{$file}");
        if (!$image) {
            $image = imagecreatefrompng("{$file}");
            $png = true;
        }

        if (!$image)
            return false;

        list($originalWidth, $originalHeight) = getimagesize($file);

        $background = imagecreatetruecolor($finalWidth, $finalHeight);
        $whiteBackground = imagecolorallocate($background, 255, 255, 255);
        imagefill($background, 0, 0, $whiteBackground);

        imagecopyresampled($background, $image, 0, ($finalHeight - $originalHeight) / 2, 0, 0, $finalWidth, $finalHeight, $originalWidth, $originalHeight); // copy the image to the background
        // Guardamos
        if (!$png)
            return imagejpeg($image, "{$newFile}");
        else
            return imagepng($image, "{$newFile}");
    }

    /**
     * FunciÃ³n para pasar una imagen png a jpg.
     *
     * @param type string $originalFile
     * @param type string $outputFile
     * @param type int $quality -- NÃºmero entre 0 (mejor compresiÃ³n) y 100 (mejor calidad).
     */
    public static function png2jpg($originalFile, $outputFile, $quality = 90)
    {
        $image = imagecreatefrompng($originalFile);
        imagejpeg($image, $outputFile, $quality);
        imagedestroy($image);

        return $outputFile;
    }

    public static function resizeFixedSize($src_file, $dst_file, $dst_width = null, $dst_height = null)
    {

        if (!file_exists($src_file) || !filesize($src_file))
            return false;

        list($src_width, $src_height, $type) = getimagesize($src_file);

        if ($dst_width == $src_width && $dst_height == $src_height)
            return true;

        if (!$src_width)
            return false;
        if (!$dst_width)
            $dst_width = $src_width;
        if (!$dst_height)
            $dst_height = $src_height;

        $size = getimagesize($src_file);
        if ($size['mime'] == 'image/pjpeg')
            $size['mime'] = 'image/jpeg';

        $file_type = strtolower(substr($size['mime'], strpos($size['mime'], '/') + 1));
        $destformat = strtolower(pathinfo($dst_file, PATHINFO_EXTENSION));
        $icfunc = "imagecreatefrom" . $file_type;
        if (!function_exists($icfunc))
            return false;

        $src_image = $icfunc($src_file);
        $width_diff = $dst_width / $src_width;
        $height_diff = $dst_height / $src_height;

        if ($width_diff > 1 && $height_diff > 1) {
            $next_width = $src_width;
            $next_height = $src_height;
        } else {
            if ($width_diff > $height_diff) {
                $next_height = $dst_height;
                $next_width = round(($src_width * $next_height) / $src_height);
                $dst_width = (int)$dst_width;
            } else {
                $next_width = $dst_width;
                $next_height = round($src_height * $dst_width / $src_width);
                $dst_height = (int)$dst_height;
            }
        }

        $dest_image = imagecreatetruecolor($dst_width, $dst_height);

        // If image is a PNG and the output is PNG, fill with transparency. Else fill with white background.
        if ($file_type == 'png' && $type == IMAGETYPE_PNG) {
            imagealphablending($dest_image, false);
            imagesavealpha($dest_image, true);
            $transparent = imagecolorallocatealpha($dest_image, 255, 255, 255, 127);
            imagefilledrectangle($dest_image, 0, 0, $dst_width, $dst_height, $transparent);
        } else {
            $white = imagecolorallocate($dest_image, 255, 255, 255);
            imagefilledrectangle($dest_image, 0, 0, $dst_width, $dst_height, $white);
        }

        imagecopyresampled($dest_image, $src_image, (int)(($dst_width - $next_width) / 2), (int)(($dst_height - $next_height) / 2), 0, 0, $next_width, $next_height, $src_width, $src_height);

        return (ImgUtil::write($file_type, $dest_image, $dst_file));
    }

    public static function write($type, $resource, $filename)
    {
        switch ($type) {
            case 'gif':
                $success = imagegif($resource, $filename);
                break;

            case 'png':
                $quality = 7;
                $success = imagepng($resource, $filename, (int)$quality);
                break;

            case 'jpg':
            case 'jpeg':
            default:
                $quality = 72;
                imageinterlace($resource, 1);
                $success = imagejpeg($resource, $filename, (int)$quality);
                break;
        }
        imagedestroy($resource);
        @chmod($filename, 0664);
        return $success;
    }

    public static function crop($src, $x, $y, $width, $height, $targ_w, $targ_h, $newPath = '')
    {
        if (empty($newPath))
            $newPath = $src;

        $img_r = imagecreatefromjpeg($src);

        $dst_r = ImageCreateTrueColor($targ_w, $targ_h);

        imagecopyresampled($dst_r, $img_r, 0, 0, intval($x), intval($y), $targ_w, $targ_h, intval($width), intval($height));
        self::write(self::imageType($src), $dst_r, $newPath);
    }

    public static function imageType($src_file)
    {
        $size = getimagesize($src_file);
        if ($size['mime'] == 'image/pjpeg')
            $size['mime'] = 'image/jpeg';

        return strtolower(substr($size['mime'], strpos($size['mime'], '/') + 1));
    }

    public static function isJpeg(&$pict)
    {
        return (\bin2hex($pict[0]) == 'ff' && \bin2hex($pict[1]) == 'd8');
    }

    public static function isPng(&$pict)
    {
        return (\bin2hex($pict[0]) == '89' && $pict[1] == 'P' && $pict[2] == 'N' && $pict[3] == 'G');
    }

    public static function fileInfo($file)
    {
        $info = pathinfo($file);
        if (empty($info))
            return null;

        $info['icon'] = 'fa fa-file-pdf-o';
        switch ($info['extension']) {
            case 'doc':
            case 'docx':
                $info['icon'] = 'fa fa-file-word-o';
                break;
            case 'png':
            case 'jpeg':
            case 'jpg':
                $info['icon'] = 'fa fa-file-photo-o';
                break;
            case 'mp4':
            case 'avi':
                $info['icon'] = 'fa fa-file-image-o';
                break;
            case 'mp3':
                $info['icon'] = 'fa fa-file-audio-o';
                break;
            case 'xls':
            case 'xlsx':
            case 'csv':
                $info['icon'] = 'fa fa-file-excel-o';
                break;
            case 'ppt':
            case 'pptx':
                $info['icon'] = 'fa fa-file-powerpoint-o';
                break;
        }

        $info['size'] = self::humanFilesize(filesize($file));

        return $info;
    }

    public static function humanFilesize($bytes, $decimals = 2)
    {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor] . ($factor > 0 ? 'b' : '');
    }
}
