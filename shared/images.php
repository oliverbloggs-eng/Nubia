<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Photo uploads for listings and blog posts. Uses PHP's built-in GD
// only. Every upload is checked, then re-drawn and saved as WebP at
// three widths, so metadata (GPS, camera details) is never kept and
// the original file is never stored.
// ---------------------------------------------------------------

const NUBIA_IMG_MAX_BYTES = 8 * 1024 * 1024;     // 8 MB
const NUBIA_IMG_MAX_PIXELS = 40000000;           // about 8000 x 5000
const NUBIA_IMG_SIZES = ['lg' => 1600, 'sm' => 800, 'th' => 400];
const NUBIA_IMG_QUALITY = 80;

/**
 * Save one uploaded photo.
 * $file   one entry from $_FILES
 * $folder subfolder of uploads, e.g. "listings/42" or "blog"
 * $name   base name, e.g. the listing slug
 * Returns ['width'=>, 'height'=>, 'files'=>['lg'=>['path','w','h'], 'sm'=>…, 'th'=>…]]
 * where path is relative to uploads_url. Throws RuntimeException with a
 * message that can be shown to staff.
 */
function nubia_image_save(array $file, string $folder, string $name): array
{
    if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('This server cannot create WebP images. Turn on the GD extension in hPanel > Advanced > PHP Configuration.');
    }
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('That photo is too large. The limit is 8 MB.');
    if ($err === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a photo to upload.');
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('The upload did not finish. Please try again.');
    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) throw new RuntimeException('The upload did not finish. Please try again.');
    if ((int)($file['size'] ?? 0) > NUBIA_IMG_MAX_BYTES || filesize($tmp) > NUBIA_IMG_MAX_BYTES) {
        throw new RuntimeException('That photo is too large. The limit is 8 MB.');
    }

    // Check what the file really is, not what its name says.
    $info = @getimagesize($tmp);
    $mime = $info['mime'] ?? '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $real = $fi ? (string)finfo_file($fi, $tmp) : '';
        if ($fi) finfo_close($fi);
        if ($real !== '' && $real !== $mime) $mime = '';
    }
    $readers = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];
    if (!$info || !isset($readers[$mime]) || !function_exists($readers[$mime])) {
        throw new RuntimeException('Upload a JPG, PNG or WebP photo.');
    }
    [$w0, $h0] = $info;
    if ($w0 < 1 || $h0 < 1 || $w0 * $h0 > NUBIA_IMG_MAX_PIXELS) throw new RuntimeException('That photo has too many pixels. Resize it below 8000 × 5000 and try again.');

    $src = @$readers[$mime]($tmp);
    if (!$src) throw new RuntimeException('That photo could not be read. Try saving it again as JPG.');

    // Phone photos are often stored sideways with a rotation flag.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $turn = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
        if ($turn) {
            $r = imagerotate($src, $turn, 0);
            if ($r) { imagedestroy($src); $src = $r; }
        }
    }
    $w0 = imagesx($src); $h0 = imagesy($src);

    $root = rtrim((string)nubia_config('uploads_dir'), '/');
    if ($root === '') throw new RuntimeException('Set uploads_dir in nubia-shared/config.php.');
    $folder = trim(preg_replace('#[^a-z0-9/_-]#i', '', $folder) ?? '', '/');
    $dir = $root . ($folder !== '' ? '/' . $folder : '');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($src);
        throw new RuntimeException('The uploads folder could not be created. Check uploads_dir in nubia-shared/config.php.');
    }
    $base = slugify($name, 60) . '-' . bin2hex(random_bytes(3));

    $out = ['width' => $w0, 'height' => $h0, 'files' => []];
    foreach (NUBIA_IMG_SIZES as $key => $target) {
        $w = min($target, $w0);
        $h = (int)max(1, round($h0 * $w / $w0));
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $w0, $h0);
        $fileName = $base . '-' . $w . 'x' . $h . '.webp';
        $ok = imagewebp($dst, $dir . '/' . $fileName, NUBIA_IMG_QUALITY);
        imagedestroy($dst);
        if (!$ok) {
            imagedestroy($src);
            foreach ($out['files'] as $f) @unlink($root . '/' . $f['path']);
            throw new RuntimeException('The photo could not be saved. Please try again.');
        }
        $out['files'][$key] = ['path' => ($folder !== '' ? $folder . '/' : '') . $fileName, 'w' => $w, 'h' => $h];
    }
    imagedestroy($src);
    return $out;
}

/** Delete the files returned by nubia_image_save(). */
function nubia_image_delete(array $paths): void
{
    $root = rtrim((string)nubia_config('uploads_dir'), '/');
    foreach ($paths as $p) {
        $p = (string)$p;
        if ($p === '' || str_contains($p, '..')) continue;
        $full = $root . '/' . ltrim($p, '/');
        if (is_file($full)) @unlink($full);
    }
}

/** Public address of an uploaded file. */
function nubia_upload_url(?string $path): string
{
    if (!$path) return '';
    return rtrim((string)nubia_config('uploads_url'), '/') . '/' . ltrim($path, '/');
}
