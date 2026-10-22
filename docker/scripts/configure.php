<?php

declare(strict_types=1);
require __DIR__.'/environment.php';

try {
    $path = '/opt/aowow/config/config.php';
    $refresh = in_array('--refresh', $argv, true);
    if (!file_exists($path) || $refresh) {
        define('AOWOW_REVISION', 1);
        $AoWoWconf = [];
        if (file_exists($path)) require $path;
        foreach (['aowow', 'world', 'auth'] as $role) {
            $AoWoWconf[$role] = array_replace($AoWoWconf[$role] ?? [], dockerDatabase(strtoupper($role), $role !== 'auth'));
        }
        $characters = dockerDatabase('CHARACTERS', false);
        if ($characters['db'] !== '') {
            $realm = filter_var(dockerValue('CHARACTERS_REALM_ID', '1'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
            if ($realm === false) throw new RuntimeException('Invalid CHARACTERS_REALM_ID.');
            $AoWoWconf['characters'][$realm] = array_replace($AoWoWconf['characters'][$realm] ?? [], $characters);
        }
        dockerWriteConfig($path, "<?php\n\nif (!defined('AOWOW_REVISION')) die('illegal access');\n\n\$AoWoWconf = ".var_export($AoWoWconf, true).";\n");
        echo "Database configuration written.\n";
    } else {
        echo "Existing database configuration retained.\n";
    }
    $security = '/opt/aowow/config/security.php';
    if (!file_exists($security)) {
        $example = file_get_contents('/opt/aowow/setup/security.php.example');
        dockerWriteConfig($security, str_replace('REPLACE_WITH_64_RANDOM_HEXADECIMAL_CHARACTERS', bin2hex(random_bytes(32)), $example));
        echo "Private security configuration created.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
