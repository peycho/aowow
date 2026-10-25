<?php

namespace Aowow {
    class Cfg {
        public static string $directory;
        public static function get(string $key) : mixed { return match ($key) {
            'CACHE_MODE' => 1, 'CACHE_DECAY' => 604800, 'CACHE_DIR' => self::$directory, default => ''
        }; }
    }
}
namespace {
    use Aowow\{CacheStore, Cfg, DibiConnection};
    if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_seo') {
        fwrite(STDERR, "Use only the disposable aowow_security_test_seo database.\n"); exit(1);
    }
    define('AOWOW_REVISION', 75); define('AOWOW_CACHE_KEY', str_repeat('33', 32));
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../includes/libs/autoload.php';
    require __DIR__.'/../includes/database.php';
    require __DIR__.'/../includes/components/cachestore.class.php';
    Cfg::$directory = require __DIR__.'/fixtures/cache-directory.php';
    $db = new DibiConnection(['driver' => 'mysqli', 'host' => getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306), 'username' => 'root', 'password' => '',
        'database' => 'aowow_security_test_seo', 'substitutes' => ['' => 'aowow_']]);
    $checks = 0;
    function check(bool $ok, string $message) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
    $tables = ['seo_cache', 'comments', 'guides', 'articles'];
    try {
        foreach ($tables as $table) {
            $db->query('DROP TABLE IF EXISTS %n', 'aowow_'.$table);
            $db->query('CREATE TABLE %n (id INT AUTO_INCREMENT PRIMARY KEY, views INT DEFAULT 0, body TEXT) ENGINE=InnoDB', 'aowow_'.$table);
        }
        $catalog = CacheStore::version('catalog');
        $db->query('INSERT INTO ::seo_cache (body) VALUES (%s)', 'Initial');
        check($db->getInsertId() === 1 && $db->getAffectedRows() === 1, 'invalidation preserves insert ID and affected-row state');
        check(CacheStore::version('catalog') !== $catalog, 'successful catalog insert invalidates the catalog');
        $loads = 0;
        $load = static function () use ($db, &$loads) { ++$loads; return $db->selectCell('SELECT COUNT(*) FROM ::seo_cache'); };
        check(CacheStore::remember('catalog', ['count'], $load) === 1, 'initial committed result');
        check(CacheStore::remember('catalog', ['count'], $load) === 1 && $loads === 1, 'warm result skips SQL');
        $catalog = CacheStore::version('catalog');
        $db->begin();
        $db->query('INSERT INTO ::seo_cache (body) VALUES (%s)', 'Rolled back');
        check(CacheStore::inTransaction() && CacheStore::version('catalog') === $catalog, 'uncommitted writes do not rotate the shared epoch');
        check(CacheStore::remember('catalog', ['count'], $load) === 2, 'transaction reads its own writes through the database');
        $db->rollback();
        check(!CacheStore::inTransaction() && CacheStore::version('catalog') === $catalog, 'rollback preserves the committed cache');
        check(CacheStore::remember('catalog', ['count'], $load) === 1 && $loads === 2, 'rollback never publishes uncommitted results');
        $db->begin();
        $db->query('INSERT INTO ::seo_cache (body) VALUES (%s)', 'Committed');
        $db->begin('cache_inner');
        $db->query('INSERT INTO ::seo_cache (body) VALUES (%s)', 'Savepoint rollback');
        $db->rollback('cache_inner');
        check(CacheStore::inTransaction() && CacheStore::version('catalog') === $catalog, 'savepoint rollback retains outer transaction isolation');
        $db->commit();
        check(!CacheStore::inTransaction() && CacheStore::version('catalog') !== $catalog, 'outer commit invalidates queued writes');
        check(CacheStore::remember('catalog', ['count'], $load) === 2 && $loads === 3, 'new committed value replaces warm data');
        $catalog = CacheStore::version('catalog');
        $db->query('START TRANSACTION');
        $db->query('UPDATE ::seo_cache SET views = 1');
        $db->query('SAVEPOINT fixture');
        $db->query('UPDATE ::seo_cache SET views = 2');
        $db->query('ROLLBACK TO SAVEPOINT fixture');
        check(CacheStore::inTransaction(), 'SQL savepoints keep cache bypass active');
        $db->query('ROLLBACK');
        check(!CacheStore::inTransaction() && CacheStore::version('catalog') === $catalog, 'SQL rollback discards pending invalidations');
        $db->query('SET autocommit = 0');
        $db->query('UPDATE ::seo_cache SET views = 3');
        check(CacheStore::version('catalog') === $catalog && CacheStore::inTransaction(), 'autocommit off defers invalidation');
        $db->query('SET autocommit = 1');
        check(CacheStore::version('catalog') !== $catalog && !CacheStore::inTransaction(), 'autocommit on commits and invalidates');
        $catalog = CacheStore::version('catalog');
        try { $db->query('UPDATE ::seo_cache SET nonexistent = 1'); } catch (Dibi\DriverException) {}
        check(CacheStore::version('catalog') === $catalog, 'failed SQL cannot invalidate committed entries');
        $db->query('INSERT INTO ::comments (body) VALUES (%s)', "mentions aowow_seo_cache and 'aowow_articles' /* aowow_guides */");
        check(CacheStore::version('catalog') === $catalog, 'names in user text do not count as touched tables');
        $guides = CacheStore::version('guides');
        $db->query('INSERT INTO ::guides (body) VALUES (%s)', 'Public');
        check(CacheStore::version('guides') !== $guides && CacheStore::version('catalog') === $catalog, 'guide changes invalidate only their scope');
        $guides = CacheStore::version('guides');
        $db->query('UPDATE ::guides SET `views` = `views` + 1 WHERE id = 1');
        check(CacheStore::version('guides') === $guides, 'live view increments do not evict public guides');
        $content = CacheStore::version('content');
        $db->query('INSERT INTO ::articles (body) VALUES (%s)', 'New article');
        check(CacheStore::version('content') !== $content && CacheStore::version('guides') !== $guides, 'article writes invalidate body fragments and published guide caches');
        echo "PASS: $checks committed-cache, transaction, savepoint and SQL invalidation checks\n";
    }
    finally {
        if (CacheStore::inTransaction()) $db->rollback();
        foreach ($tables as $table) $db->query('DROP TABLE IF EXISTS %n', 'aowow_'.$table);
    }
}
