<?php
declare(strict_types=1);
namespace App\Services;

use Mini\Database as DB;

final class Branding
{
    public const MAX_BYTES=2097152;
    private static ?array $current=null;

    public static function current(): array
    {
        return self::$current ??= DB::fetch('SELECT revision, logo IS NOT NULL AS has_logo, favicon IS NOT NULL AS has_favicon FROM app_branding WHERE id=1');
    }

    public static function url(string $kind): string
    {
        $current=self::current();
        return !empty($current['has_'.$kind]) ? \url('branding/'.$kind.'/'.$current['revision']) : \asset('favicon.svg');
    }

    public static function save(array $files): void
    {
        // Validate both files before changing either saved image.
        $logo=self::upload($files['logo']??[], 'logo',256);
        $favicon=self::upload($files['favicon']??[], 'favicon',64);
        if ($logo===null && $favicon===null) Finance::fail('branding','Choose at least one image to upload.');
        $changes=['revision'=>bin2hex(random_bytes(16))];
        if ($logo!==null) $changes['logo']=$logo;
        if ($favicon!==null) $changes['favicon']=$favicon;
        DB::transaction(function () use ($changes) {
            DB::statement('SELECT id FROM app_branding WHERE id=1 FOR UPDATE');
            DB::update('app_branding',1,$changes);
        });
        self::$current=null;
    }

    private static function upload(array $file, string $field, int $size): ?string
    {
        if (!$file || ($file['error']??null)===UPLOAD_ERR_NO_FILE) return null;
        if (in_array($file['error']??null,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true) || (is_int($file['size']??null) && $file['size']>self::MAX_BYTES)) Finance::fail($field,'Choose an image smaller than 2 MB.');
        if (($file['error']??null)!==UPLOAD_ERR_OK || !is_string($file['tmp_name']??null) || !is_uploaded_file($file['tmp_name'])) Finance::fail($field,'The image could not be uploaded. Please choose it again.');
        $bytes=file_get_contents($file['tmp_name']);
        if ($bytes===false || strlen($bytes)>self::MAX_BYTES) Finance::fail($field,'Choose an image smaller than 2 MB.');
        $info=@getimagesizefromstring($bytes);
        if (!$info || !in_array($info[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG,IMAGETYPE_WEBP],true)) Finance::fail($field,'Choose a valid PNG, JPG, or WebP image.');
        if ($info[0]<16 || $info[1]<16 || $info[0]>2048 || $info[1]>2048) Finance::fail($field,'Image dimensions must be between 16 and 2048 pixels.');
        $source=@imagecreatefromstring($bytes);
        if (!$source) Finance::fail($field,'Choose a valid PNG, JPG, or WebP image.');
        $canvas=imagecreatetruecolor($size,$size);
        try {
            imagealphablending($canvas,false); imagesavealpha($canvas,true);
            imagefill($canvas,0,0,imagecolorallocatealpha($canvas,0,0,0,127));
            $ratio=min($size/$info[0],$size/$info[1]);
            $width=max(1,(int)round($info[0]*$ratio)); $height=max(1,(int)round($info[1]*$ratio));
            imagecopyresampled($canvas,$source,(int)(($size-$width)/2),(int)(($size-$height)/2),0,0,$width,$height,$info[0],$info[1]);
            ob_start();
            try { imagepng($canvas,null,6); $png=ob_get_contents(); }
            finally { ob_end_clean(); }
            return $png;
        } finally { imagedestroy($source); imagedestroy($canvas); }
    }

    public static function reset(string $kind): void
    {
        if (!in_array($kind,['logo','favicon'],true)) Finance::fail('branding','Please select a valid option.');
        DB::update('app_branding',1,[$kind=>null,'revision'=>bin2hex(random_bytes(16))]);
        self::$current=null;
    }

    public static function image(string $kind, string $revision): ?string
    {
        if (!in_array($kind,['logo','favicon'],true) || !preg_match('/^[a-f0-9]{32}$/D',$revision)) return null;
        $row=DB::fetch("SELECT `$kind` AS image FROM app_branding WHERE id=1 AND revision=?",[$revision]);
        return $row['image']??null;
    }
}
