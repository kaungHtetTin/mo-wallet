<?php
/** Isolated upload, image delivery, and reset checks. */
use App\Services\Branding;
use Mini\Database as DB;

function brandingPicture(int $width=64,int $height=64,string $format='png'): string {
    $picture=imagecreatetruecolor($width,$height);
    imagefill($picture,0,0,imagecolorallocate($picture,40,100,72));
    ob_start();
    try {
        match($format) { 'jpeg'=>imagejpeg($picture), 'webp'=>imagewebp($picture), default=>imagepng($picture) };
        return ob_get_contents();
    } finally { ob_end_clean(); imagedestroy($picture); }
}

function brandingUpload(array $images): array {
    global $port,$cookie;
    $paths=[]; $fields=tokens(request('settings')['body']);
    try {
        foreach ($images as $field=>$bytes) {
            $paths[]= $temp=tempnam(sys_get_temp_dir(),'mowallet-branding-');
            file_put_contents($temp,$bytes);
            $fields[$field]=new CURLFile($temp,'image/png',$field.'.png');
        }
        $curl=curl_init("http://127.0.0.1:$port/settings/branding");
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields,CURLOPT_TIMEOUT=>10]);
        $response=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
        return ['status'=>$status,'response'=>$response];
    } finally { foreach ($paths as $file) @unlink($file); }
}

$beforeBranding=DB::fetch('SELECT * FROM app_branding WHERE id=1');
check($beforeBranding['logo']===null && $beforeBranding['favicon']===null,'Branding starts with built-in defaults');
check(str_contains(request('settings')['body'],'Logo &amp; favicon'),'Branding controls render in Settings');
check(request('settings/branding',[])['status']===419,'Branding upload requires CSRF');
invalid(fn()=>Branding::save([]),'Empty branding upload rejected');
check(brandingUpload(['logo'=>brandingPicture(80,40)])['status']===302,'Logo uploaded through multipart form');
$row=DB::fetch('SELECT * FROM app_branding WHERE id=1');
check($row['logo']!==null && $row['favicon']===null,'Logo upload preserves default favicon');
$logoInfo=getimagesizefromstring($row['logo']);
check($logoInfo[0]===256 && $logoInfo[1]===256 && $logoInfo[2]===IMAGETYPE_PNG,'Logo normalized to 256px PNG');
$decoded=imagecreatefromstring($row['logo']);
check((imagecolorat($decoded,0,0)>>24)===127,'Non-square logo keeps transparent padding'); imagedestroy($decoded);
$logoUrl='branding/logo/'.$row['revision'];
$imageResponse=request($logoUrl);
check($imageResponse['status']===200 && str_contains($imageResponse['headers'],'Content-Type: image/png') && $imageResponse['body']===$row['logo'],'Logo response delivers validated PNG bytes');
check(str_contains($imageResponse['headers'],'immutable'),'Versioned branding images are cacheable');
check(str_contains(request('')['body'],'class="app-logo"'),'Uploaded logo appears in app header');

// Public image delivery is needed on login and for browser favicons.
$public=curl_init("http://127.0.0.1:$port/$logoUrl");
curl_setopt_array($public,[CURLOPT_RETURNTRANSFER=>true]);
$publicBytes=curl_exec($public); $publicStatus=curl_getinfo($public,CURLINFO_HTTP_CODE); curl_close($public);
check($publicStatus===200 && $publicBytes===$row['logo'],'Branding image works before login');

check(brandingUpload(['favicon'=>brandingPicture(64,64,'jpeg')])['status']===302,'JPEG favicon upload accepted');
$both=DB::fetch('SELECT * FROM app_branding WHERE id=1');
check($both['logo']===$row['logo'] && $both['favicon']!==null,'Favicon upload preserves existing logo');
$faviconInfo=getimagesizefromstring($both['favicon']);
check($faviconInfo[0]===64 && $faviconInfo[1]===64 && $faviconInfo[2]===IMAGETYPE_PNG,'Favicon generated as 64px PNG');
check($both['revision']!==$row['revision'],'Replacing an image changes its cache version');
check(str_contains(request('reports')['body'],'branding/favicon/'.$both['revision']),'Custom favicon is linked from every app page');
check(request($logoUrl)['status']===404,'Outdated branding URL cannot serve a newer image under an old cache key');
check(request('branding/other/'.$both['revision'])['status']===404,'Unknown branding kind rejected');
check(request('branding/logo/invalid')['status']===404,'Malformed branding version rejected');

$beforeRejected=$both;
brandingUpload(['logo'=>brandingPicture(),'favicon'=>'<?php echo "not an image"; ?>']);
check(str_contains(request('settings')['body'],'Choose a valid PNG, JPG, or WebP image.'),'Invalid upload shows an actionable error');
$afterRejected=DB::fetch('SELECT * FROM app_branding WHERE id=1');
check($afterRejected===$beforeRejected,'Invalid second image leaves both saved images unchanged');
brandingUpload(['logo'=>brandingPicture(8,8)]);
check(str_contains(request('settings')['body'],'Image dimensions must be between 16 and 2048 pixels.'),'Tiny image is rejected');
brandingUpload(['logo'=>brandingPicture(2049,16)]);
check(str_contains(request('settings')['body'],'Image dimensions must be between 16 and 2048 pixels.'),'Oversized dimensions rejected before decoding');
brandingUpload(['logo'=>str_repeat('x',2097153)]);
check(str_contains(request('settings')['body'],'Choose an image smaller than 2 MB.'),'Oversized image upload rejected');
check(DB::fetch('SELECT * FROM app_branding WHERE id=1')===$beforeRejected,'Rejected image uploads preserve saved branding');
check(brandingUpload(['logo'=>brandingPicture(64,64,'webp')])['status']===302,'WebP logo upload supported');

$csrf=tokens(request('settings')['body']);
check(request('settings/branding/reset/logo',$csrf)['status']===302,'Logo reset POST succeeds');
$reset=DB::fetch('SELECT * FROM app_branding WHERE id=1');
check($reset['logo']===null && $reset['favicon']!==null,'Reset logo preserves custom favicon');
check(!str_contains(request('')['body'],'class="app-logo"'),'Header falls back to built-in logo after reset');
request('settings/branding/reset/favicon',tokens(request('settings')['body']));
check(DB::fetchValue('SELECT favicon FROM app_branding WHERE id=1')===null,'Favicon reset restores built-in default');
check(str_contains(request('settings')['body'],'assets/favicon.svg'),'Default favicon URL restored');
