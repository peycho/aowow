<?php

// Private disposable storage only; never use the application's configured cache.
$fixtureCacheDirectory = sys_get_temp_dir().'/aowow-cache-fixture-'.bin2hex(random_bytes(8));
mkdir($fixtureCacheDirectory, 0700);
register_shutdown_function(static function () use ($fixtureCacheDirectory) : void {
    $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixtureCacheDirectory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($paths as $path)
        if ($path->isDir() && !$path->isLink()) rmdir($path->getPathname()); else unlink($path->getPathname());
    rmdir($fixtureCacheDirectory);
});
return $fixtureCacheDirectory;
