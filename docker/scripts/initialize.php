<?php

declare(strict_types=1);

set_error_handler(static function (int $code, string $message, string $file, int $line) : never {
    throw new ErrorException($message, 0, $code, $file, $line);
});

// Only image-owned paths are refreshed. Generated assets are never pruned.
$paths = ['cache', 'datasets', 'static/download', 'static/widgets', 'static/js',
    'static/uploads', 'static/images/wow', 'static/wowsounds', 'static/modelviewer', 'setup/mpqdata'];
$manifestPath = '/opt/aowow/config/.docker-assets.json';
$old = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR) : [];
$current = [];

function safeTarget(string $relative) : string
{
    if (str_contains($relative, '..') || str_starts_with($relative, '/')) throw new RuntimeException('Unsafe asset path.');
    $target = '/opt/aowow';
    foreach (explode('/', $relative) as $part) {
        $target .= '/'.$part;
        if (is_link($target)) throw new RuntimeException('Symlink in generated asset path: '.$relative);
    }
    return $target;
}

foreach ($paths as $path) {
    $target = safeTarget($path);
    if (!is_dir($target)) mkdir($target, 0755, true);
    chown($target, 'www-data'); chgrp($target, 'www-data');
    $source = '/usr/local/share/aowow-seed/'.$path;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $file) {
        if ($file->isLink()) continue;
        $relative = $path.'/'.substr($file->getPathname(), strlen($source) + 1);
        $dest = safeTarget($relative);
        if ($file->isDir()) {
            if (!is_dir($dest)) mkdir($dest, 0755, true);
            chown($dest, 'www-data'); chgrp($dest, 'www-data');
            continue;
        }
        if (!$file->isFile()) continue;
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
        if (!copy($file->getPathname(), $dest)) throw new RuntimeException('Cannot refresh '.$relative);
        chmod($dest, $file->getPerms() & 0777);
        chown($dest, 'www-data'); chgrp($dest, 'www-data');
        $current[] = $relative;
    }
}
foreach (array_diff($old, $current) as $relative) {
    // Validate the manifest against the approved volume roots before removal.
    if (!array_filter($paths, fn($path) => str_starts_with($relative, $path.'/'))) throw new RuntimeException('Invalid asset manifest.');
    $dest = safeTarget($relative);
    if (is_file($dest) && !unlink($dest)) throw new RuntimeException('Cannot retire '.$relative);
}
foreach (['cache/sessions', 'cache/setup'] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    chown($dir, 'www-data'); chgrp($dir, 'www-data'); chmod($dir, 0700);
}
chmod('cache', 0700);
if (!file_exists('static/download/robots.txt')) {
    $robots = file_get_contents('setup/tools/filegen/templates/robots.txt.in');
    file_put_contents('static/download/robots.txt', preg_replace('/^Sitemap:.*$/m', '', $robots));
    chown('static/download/robots.txt', 'www-data'); chgrp('static/download/robots.txt', 'www-data');
}
file_put_contents($manifestPath, json_encode($current, JSON_THROW_ON_ERROR));
echo "Persistent directories prepared; shipped assets refreshed.\n";
