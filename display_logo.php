<?php
/**
 * Sert le logo de l'établissement (uploads/logos) ou un fallback discret.
 * Le logo plateforme (SeSanté) ne doit pas apparaître dans l'espace privé d'un abonné.
 */

/**
 * Icône clinique générique (sans marque plateforme).
 */
function display_logo_render_generic_clinic(int $width = 180, int $height = 60): void
{
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);

    $teal = imagecolorallocate($image, 23, 161, 184);
    $tealDark = imagecolorallocate($image, 15, 122, 138);
    $white = imagecolorallocate($image, 255, 255, 255);

    $cx = (int) ($width * 0.22);
    $cy = (int) ($height / 2);
    $r = (int) min($height * 0.38, 28);
    imagefilledellipse($image, $cx, $cy, $r * 2, $r * 2, $teal);
    imageellipse($image, $cx, $cy, $r * 2, $r * 2, $tealDark);
    imagefilledrectangle($image, $cx - (int) ($r * 0.35), $cy - (int) ($r * 0.55), $cx + (int) ($r * 0.35), $cy + (int) ($r * 0.55), $white);
    imagefilledrectangle($image, $cx - (int) ($r * 0.55), $cy - (int) ($r * 0.18), $cx + (int) ($r * 0.55), $cy + (int) ($r * 0.18), $white);

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    imagepng($image);
    imagedestroy($image);
}

ob_start();

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/config/SystemParameters.php';

    $systemParams = SystemParameters::getInstance();
    $logoPath = $systemParams->getLogoPath();

    if ($logoPath && file_exists($logoPath) && is_readable($logoPath)) {
        ob_end_clean();

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $logoPath) ?: 'image/png';
        finfo_close($finfo);

        $maxW = isset($_GET['w']) ? max(0, (int) $_GET['w']) : 0;
        $maxH = isset($_GET['h']) ? max(0, (int) $_GET['h']) : 0;

        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
        $mtime = @filemtime($logoPath);
        if ($mtime) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        }

        if ($maxW > 0 && $maxH > 0 && extension_loaded('gd')) {
            $info = @getimagesize($logoPath);
            if ($info) {
                [$srcW, $srcH] = $info;
                $ratio = min($maxW / max(1, $srcW), $maxH / max(1, $srcH), 1);
                $dstW = max(1, (int) round($srcW * $ratio));
                $dstH = max(1, (int) round($srcH * $ratio));

                switch ($info[2]) {
                    case IMAGETYPE_JPEG:
                        $src = @imagecreatefromjpeg($logoPath);
                        break;
                    case IMAGETYPE_PNG:
                        $src = @imagecreatefrompng($logoPath);
                        break;
                    default:
                        $src = false;
                }

                if ($src) {
                    $dst = imagecreatetruecolor($dstW, $dstH);
                    if ($info[2] === IMAGETYPE_PNG) {
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                        imagefill($dst, 0, 0, $transparent);
                    }
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
                    if ($info[2] === IMAGETYPE_PNG) {
                        imagepng($dst);
                    } else {
                        imagejpeg($dst, null, 90);
                    }
                    imagedestroy($src);
                    imagedestroy($dst);
                    exit;
                }
            }
        }

        readfile($logoPath);
        exit;
    }

    ob_end_clean();

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $tenantContext = !empty($_SESSION['tenant_id']) || isset($_GET['tenant']);

    $maxW = isset($_GET['w']) ? max(0, (int) $_GET['w']) : 180;
    $maxH = isset($_GET['h']) ? max(0, (int) $_GET['h']) : 60;

    if ($tenantContext) {
        display_logo_render_generic_clinic(max(64, $maxW), max(32, $maxH));
        exit;
    }

    // Hors espace abonné (pas de tenant en session) : logo plateforme pour pages publiques / legacy
    require_once __DIR__ . '/includes/platform_brand.php';
    $platformLogo = __DIR__ . '/' . ltrim(platform_logo_path(), '/\\');
    if (is_file($platformLogo) && is_readable($platformLogo)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $platformLogo) ?: 'image/png';
        finfo_close($finfo);
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=86400');
        readfile($platformLogo);
        exit;
    }

    display_logo_render_generic_clinic(max(64, $maxW), max(32, $maxH));
    exit;
} catch (Throwable $e) {
    ob_end_clean();
    error_log('display_logo.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: image/png');
    $image = imagecreatetruecolor(1, 1);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagepng($image);
    imagedestroy($image);
    exit;
}
