<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Signed, bounded data caches share the configured response-cache backends. */
final class CacheStore
{
    private static ?\Memcached $memcached = null;
    private static array $transactions = [];
    private static array $failedScopes = [];

    public static function enabled() : bool
    {
        if (!class_exists(Cfg::class, false) || !Cfg::get('CACHE_MODE') || (int)Cfg::get('CACHE_DECAY') <= 0)
            return false;
        return self::authenticated();
    }

    private static function authenticated() : bool
    {
        if (!class_exists(CacheEnvelope::class))
            require_once __DIR__.'/cacheenvelope.class.php';
        return CacheEnvelope::enabled();
    }

    private static function root() : string
    {
        return rtrim((string)(Cfg::get('CACHE_DIR') ?: 'cache/template'), '/');
    }

    /** Epochs survive entry eviction and are shared by FPM workers and maintenance CLI. */
    public static function version(string $scope) : ?string
    {
        if (!in_array($scope, ['catalog', 'content', 'guides'], true) || isset(self::$failedScopes[$scope]) || !self::enabled())
            return null;
        $directory = self::root().'/.epochs';
        if (!CacheEnvelope::writeDirectory($directory))
            return null;
        $file = $directory.'/'.$scope;
        if (is_link($file))
            return null;
        $value = is_file($file) ? @file_get_contents($file, false, null, 0, 1024) : false;
        $entry = CacheEnvelope::open('epoch:'.$scope, $value);
        if (is_string($entry[0] ?? null) && preg_match('/^[a-f0-9]{32}$/D', $entry[0]))
            return $entry[0];

        $token = bin2hex(random_bytes(16));
        $sealed = CacheEnvelope::seal('epoch:'.$scope, $token, [null, null], 10 * 365 * 86400);
        return $sealed !== null && CacheEnvelope::writeFile($file, $sealed) ? $token : null;
    }

    public static function invalidate(string ...$scopes) : void
    {
        // Keep epochs current while caching is disabled too: enabling it again
        // must not resurrect entries from before an import/configuration change.
        if (!class_exists(Cfg::class, false) || !self::authenticated())
            return;
        foreach ($scopes ?: ['catalog', 'content', 'guides'] as $scope)
        {
            if (!in_array($scope, ['catalog', 'content', 'guides'], true))
                continue;
            $directory = self::root().'/.epochs';
            $file = $directory.'/'.$scope;
            $sealed = CacheEnvelope::seal('epoch:'.$scope, bin2hex(random_bytes(16)), [null, null], 10 * 365 * 86400);
            if ($sealed === null || !CacheEnvelope::writeDirectory($directory) || is_link($file) || !CacheEnvelope::writeFile($file, $sealed))
            {
                // Never reuse the old generation in this request after a failed invalidation.
                self::$failedScopes[$scope] = true;
                if (!is_link($file))
                    @unlink($file);
            }
        }
    }

    public static function transaction(int $connection, bool $active) : void
    {
        if ($active)
            self::$transactions[$connection] = true;
        else
            unset(self::$transactions[$connection]);
    }

    public static function inTransaction() : bool
    {
        return (bool)self::$transactions;
    }

    /** Null results (including database failures) are never published as successful cache entries. */
    public static function remember(string $scope, array $identity, callable $loader, ?int $lifetime = null) : mixed
    {
        if (!self::enabled() || self::inTransaction() || !($version = self::version($scope)))
            return $loader();
        $key = 'data:'.$scope.':'.hash('sha256', serialize([
            1, $version, Cfg::get('HOST_URL'), Cfg::get('STATIC_URL'), Cfg::get('LOCALES'),
            Cfg::get('SOUNDS_ENABLE'), Cfg::get('PROFILER_ENABLE'), $identity
        ]));
        $mode = (int)Cfg::get('CACHE_MODE');
        $refresh = isset($_GET['refresh']) && class_exists(User::class, false) && User::isInGroup(U_GROUP_ADMIN | U_GROUP_BUREAU | U_GROUP_DEV);
        $entry = null;
        if (!$refresh && ($mode & CACHE_MODE_MEMCACHED))
            $entry = CacheEnvelope::open($key, CacheEnvelope::fetchMemcached(CacheEnvelope::memcachedKey($key)));
        if (!$refresh && $entry === null && ($mode & CACHE_MODE_FILECACHE))
        {
            $file = CacheEnvelope::filePath(self::root(), $key);
            if (is_file($file) && !is_link($file))
                $entry = CacheEnvelope::open($key, @file_get_contents($file, false, null, 0, CacheEnvelope::FILE_MAX_BYTES + 1));
        }
        if (is_string($entry[0] ?? null))
        {
            try { return json_decode($entry[0], true, 128, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { /* Treat a malformed authenticated data entry as a miss. */ }
        }

        $result = $loader();
        if ($result === null || self::inTransaction() || self::version($scope) !== $version)
            return $result;
        $lifetime = min((int)Cfg::get('CACHE_DECAY'), $lifetime ?? (int)Cfg::get('CACHE_DECAY'));
        try { $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE); }
        catch (\JsonException) { return $result; }
        $sealed = CacheEnvelope::seal($key, $json, [null, null], $lifetime);
        if ($sealed !== null)
        {
            if ($mode & CACHE_MODE_MEMCACHED)
            {
                if (self::$memcached === null && class_exists('\Memcached'))
                {
                    self::$memcached = new \Memcached();
                    self::$memcached->setOption(\Memcached::OPT_COMPRESSION, false);
                    self::$memcached->addServer('localhost', 11211);
                }
                self::$memcached?->set(CacheEnvelope::memcachedKey($key), $sealed, time() + $lifetime);
            }
            if ($mode & CACHE_MODE_FILECACHE)
                CacheEnvelope::writeBoundedFile(self::root(), $key, $sealed);
        }
        return $result;
    }
}
