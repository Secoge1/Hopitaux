<?php
/**
 * Génère les icônes PNG requises pour la PWA (Chrome, Edge, iOS).
 * Usage : php scripts/generate_pwa_icons.php
 */

$outDir = __DIR__ . '/../assets/pwa';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "Extension GD requise.\n");
    exit(1);
}

function drawPwaIcon(int $size)
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    $blue  = imagecolorallocate($img, 25, 118, 210);
    $white = imagecolorallocate($img, 255, 255, 255);
    $radius = (int) round($size * 0.18);
    $margin = (int) round($size * 0.06);

    imagefilledrectangle($img, $margin + $radius, $margin, $size - $margin - $radius, $size - $margin, $blue);
    imagefilledrectangle($img, $margin, $margin + $radius, $size - $margin, $size - $margin - $radius, $blue);
    imagefilledellipse($img, $margin + $radius, $margin + $radius, $radius * 2, $radius * 2, $blue);
    imagefilledellipse($img, $size - $margin - $radius, $margin + $radius, $radius * 2, $radius * 2, $blue);
    imagefilledellipse($img, $margin + $radius, $size - $margin - $radius, $radius * 2, $radius * 2, $blue);
    imagefilledellipse($img, $size - $margin - $radius, $size - $margin - $radius, $radius * 2, $radius * 2, $blue);

    $cx  = (int) ($size / 2);
    $cy  = (int) ($size / 2);
    $arm = max(2, (int) round($size * 0.09));
    $len = (int) round($size * 0.28);
    imagefilledrectangle($img, $cx - $arm, $cy - $len, $cx + $arm, $cy + $len, $white);
    imagefilledrectangle($img, $cx - $len, $cy - $arm, $cx + $len, $cy + $arm, $white);

    return $img;
}

$sizes = [
    'icon-96x96.png'           => 96,
    'icon-192x192.png'         => 192,
    'icon-512x512.png'         => 512,
    'apple-touch-icon.png'     => 180,
];

foreach ($sizes as $filename => $px) {
    $path = $outDir . '/' . $filename;
    $img  = drawPwaIcon($px);
    imagepng($img, $path, 9);
    imagedestroy($img);
    echo "OK {$filename} ({$px}px)\n";
}

echo "Icônes PWA générées dans assets/pwa/\n";
