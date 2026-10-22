<?php

declare(strict_types=1);

define('AOWOW_SETUP', true);
require '/opt/aowow/includes/kernel.php';

$checks = 0;
function check(bool $ok, string $message) : void {
    global $checks;
    ++$checks;
    if (!$ok) { fwrite(STDERR, 'FAIL: '.$message."\n"); exit(1); }
}

$normalize = new ReflectionMethod(Aowow\DB::class, 'connectionOptions');
foreach ([
    [['host' => 'db', 'port' => '3307'], ['host' => 'db', 'port' => 3307]],
    [['host' => 'db:3307'], ['host' => 'db', 'port' => 3307]],
    [['host' => 'db:3306', 'port' => 3307], ['host' => 'db', 'port' => 3307]],
    [['host' => '::1', 'port' => 3307], ['host' => '::1', 'port' => 3307]],
    [['host' => '[::1]:3307'], ['host' => '::1', 'port' => 3307]],
    [['host' => 'db', 'port' => ''], ['host' => 'db', 'port' => 0]],
] as [$input, $expected]) {
    check($normalize->invoke(null, $input) === $expected, 'Port normalization');
}

require '/opt/aowow/config/config.php';
check(Aowow\DB::test($AoWoWconf['world']), 'Explicit non-default port connection test');
$legacy = $AoWoWconf['world'];
$legacy['host'] .= ':'.$legacy['port'];
unset($legacy['port']);
check(Aowow\DB::test($legacy), 'Legacy host:port connection test');
Aowow\DB::load(DB_WORLD, $legacy);
check((int)Aowow\DB::World()->selectCell('SELECT 1') === 1, 'Legacy Dibi connection');
Aowow\DB::load(DB_WORLD, $AoWoWconf['world']);
check((int)Aowow\DB::World()->selectCell('SELECT 1') === 1, 'Explicit Dibi connection');
check((int)Aowow\DB::Aowow()->selectCell('SELECT COUNT(*) FROM aowow_dbversion') > 0, 'Fresh application import');
foreach (['world', 'auth'] as $role) {
    $db = $AoWoWconf[$role];
    $link = new mysqli($db['host'], $db['user'], $db['pass'], $db['db'], $db['port']);
    check($link->query('SELECT 1') !== false, $role.' read grant');
    mysqli_report(MYSQLI_REPORT_OFF);
    check(@$link->query('CREATE TABLE forbidden_write (id INT)') === false, $role.' rejects writes');
}
foreach ($AoWoWconf['characters'] as $db) {
    $link = new mysqli($db['host'], $db['user'], $db['pass'], $db['db'], $db['port']);
    check($link->query('SELECT 1') !== false, 'Characters read grant');
    check(@$link->query('CREATE TABLE forbidden_write (id INT)') === false, 'Characters rejects writes');
}
check(file_put_contents('cache/docker-smoke', 'persistent-cache') !== false, 'CLI cache write');
check(file_put_contents('static/uploads/docker-smoke.txt', 'persistent-upload') !== false, 'CLI upload write');
check(file_put_contents('datasets/docker-smoke', 'persistent-dataset') !== false, 'CLI dataset write');
check(fileowner('cache/docker-smoke') === posix_geteuid(), 'CLI/web cache ownership');
check(is_writable('config/config.php'), 'Maintenance configuration writable');
echo 'PASS: '.$checks." Docker/PHP/database checks\n";
