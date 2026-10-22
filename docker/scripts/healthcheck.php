<?php

declare(strict_types=1);

try {
    $http = curl_init('http://127.0.0.1:8080/_docker-health.php');
    curl_setopt_array($http, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
    $body = curl_exec($http);
    if (curl_getinfo($http, CURLINFO_RESPONSE_CODE) !== 200 || $body !== "aowow-php-ok\n") throw new RuntimeException();
    define('AOWOW_REVISION', 1);
    $AoWoWconf = [];
    require '/opt/aowow/config/config.php';
    $databases = [$AoWoWconf['aowow'] ?? [], $AoWoWconf['world'] ?? []];
    if (!empty($AoWoWconf['auth']['db'])) $databases[] = $AoWoWconf['auth'];
    foreach ($AoWoWconf['characters'] ?? [] as $db) if (!empty($db['db'])) $databases[] = $db;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    foreach ($databases as $db) {
        if (empty($db['db'])) throw new RuntimeException();
        $link = mysqli_init();
        $link->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
        $link->options(MYSQLI_OPT_READ_TIMEOUT, 2);
        $link->real_connect($db['host'], $db['user'], $db['pass'], $db['db'], (int)($db['port'] ?? 3306));
        $link->query('SELECT 1');
        $link->close();
    }
    echo "HTTP/PHP and configured databases ready.\n";
} catch (Throwable) {
    fwrite(STDERR, "HTTP/PHP or a configured database is unavailable; check setup and database connectivity.\n");
    exit(1);
}
