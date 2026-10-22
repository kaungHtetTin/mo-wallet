<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\Branding;
use App\ValidationException;

final class BrandingController
{
    public function save(): void
    {
        try {
            Branding::save($_FILES);
            redirect('settings',t('Logo and favicon settings saved.'));
        } catch (ValidationException $e) { AuthController::errors($e,'settings'); }
    }

    public function reset(string $kind): void
    {
        try { Branding::reset($kind); redirect('settings',t('Default branding restored.')); }
        catch (ValidationException $e) { AuthController::errors($e,'settings'); }
    }

    public function image(string $kind, string $version): void
    {
        $image=Branding::image($kind,$version);
        if ($image===null) { http_response_code(404); return; }
        $etag='"'.$kind.'-'.$version.'"';
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: '.$etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag) { http_response_code(304); return; }
        header('Content-Length: '.strlen($image));
        echo $image;
    }
}
