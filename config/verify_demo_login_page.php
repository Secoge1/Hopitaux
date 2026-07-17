<?php
$_GET['demo_try'] = '1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/Hopitaux/login.php';

ob_start();
try {
    include __DIR__ . '/../login.php';
} catch (Throwable $e) {
    ob_end_clean();
    echo 'FATAL: ' . $e->getMessage();
    exit(1);
}
$out = ob_get_clean();
if (headers_list()) {
    echo 'HEADERS:' . PHP_EOL;
    foreach (headers_list() as $h) {
        echo $h . PHP_EOL;
    }
} else {
    echo 'NO REDIRECT - body length: ' . strlen($out) . PHP_EOL;
    if (preg_match('/pub-alert|error|alert|Erreur/i', $out, $m)) {
        echo 'Found error text in page' . PHP_EOL;
    }
    echo substr(strip_tags($out), 0, 500) . PHP_EOL;
}
