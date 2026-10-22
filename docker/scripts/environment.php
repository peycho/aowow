<?php

declare(strict_types=1);

function dockerValue(string $name, string $default = '') : string
{
    $value = getenv($name);
    $file = getenv($name.'_FILE');
    if ($file !== false && $file !== '') {
        if ($value !== false && $value !== '') {
            throw new RuntimeException('Set only one of '.$name.' and '.$name.'_FILE.');
        }
        if (!is_readable($file)) {
            throw new RuntimeException('Cannot read secret for '.$name.'.');
        }
        return rtrim(file_get_contents($file), "\r\n");
    }
    return $value === false ? $default : $value;
}

function dockerDatabase(string $role, bool $required = true) : array
{
    $name = dockerValue($role.'_DB_NAME');
    if (!$required && $name === '') {
        return ['host' => '', 'user' => '', 'pass' => '', 'db' => '', 'prefix' => ''];
    }
    $host = dockerValue($role.'_DB_HOST');
    $user = dockerValue($role.'_DB_USER');
    $port = filter_var(dockerValue($role.'_DB_PORT', '3306'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($name === '' || $host === '' || $user === '' || $port === false) {
        throw new RuntimeException('Incomplete database settings for '.$role.'.');
    }
    return ['host' => $host, 'port' => $port, 'user' => $user,
        'pass' => dockerValue($role.'_DB_PASSWORD'), 'db' => $name,
        'prefix' => $role === 'AOWOW' ? 'aowow_' : ''];
}

function dockerWriteConfig(string $path, string $content) : void
{
    $tmp = tempnam(dirname($path), '.docker-config-');
    try {
        if (file_put_contents($tmp, $content) === false || !chmod($tmp, 0640) || !rename($tmp, $path)) {
            throw new RuntimeException('Cannot write private configuration.');
        }
    } finally {
        if (is_file($tmp)) unlink($tmp);
    }
}
