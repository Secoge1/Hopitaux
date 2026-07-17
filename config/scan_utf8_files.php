<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$invalid = [];
$latin = [];
$replacement = [];
foreach ($it as $f) {
    if (!in_array($f->getExtension(), ['php', 'html', 'js', 'css'], true)) {
        continue;
    }
    $p = $f->getPathname();
    if (strpos($p, 'vendor') !== false || strpos($p, 'node_modules') !== false) {
        continue;
    }
    $c = file_get_contents($p);
    if ($c === false) {
        continue;
    }
    if (!mb_check_encoding($c, 'UTF-8')) {
        $invalid[] = str_replace($root . DIRECTORY_SEPARATOR, '', $p);
        continue;
    }
  // Windows-1252 French text saved as single-byte in UTF-8 context: common chars é è ê à ù
    if (preg_match('/\xE9|\xE8|\xEA|\xE0|\xF9|\xE7/', $c) && strpos($c, 'charset=UTF-8') === false) {
        // might be latin1 - check if also has proper utf8 sequences
        if (!preg_match('/\xC3[\xA0-\xBF]/', $c)) {
            $latin[] = str_replace($root . DIRECTORY_SEPARATOR, '', $p);
        }
    }
    if (strpos($c, "\xEF\xBF\xBD") !== false) {
        $replacement[] = str_replace($root . DIRECTORY_SEPARATOR, '', $p);
    }
}
echo "UTF-8 replacement char (U+FFFD):\n";
foreach ($replacement as $f) {
    echo "  $f\n";
}
echo "\nInvalid UTF-8:\n";
foreach ($invalid as $f) {
    echo "  $f\n";
}
echo "\nLikely Latin-1 (no UTF-8 multibyte accents):\n";
foreach ($latin as $f) {
    echo "  $f\n";
}
