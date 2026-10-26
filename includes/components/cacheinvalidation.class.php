<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Observe successful application-database writes without changing SQL results or insert IDs. */
final class CacheInvalidation
{
    private bool $transaction = false;
    private bool $autocommit = true;
    private array $pending = [];

    public function __construct(private string $prefix, private int $connection) {}

    public function __invoke(\Dibi\Event $event) : void
    {
        if ($event->result instanceof \Throwable)
            return;
        $sql = preg_replace('~\A(?:\s+|/\*.*?\*/|--[^\n]*(?:\n|$)|\#[^\n]*(?:\n|$))*~s', '', $event->sql);
        // Dibi puts the savepoint name in sql for its transaction events.
        // Releasing/rolling back one savepoint does not end the outer transaction.
        if (($event->type & \Dibi\Event::TRANSACTION) && $sql !== '')
            return;
        $begin = $event->type === \Dibi\Event::BEGIN || preg_match('/^(?:START\s+TRANSACTION|BEGIN)\b/i', $sql);
        $commit = $event->type === \Dibi\Event::COMMIT || preg_match('/^COMMIT\b/i', $sql);
        $rollback = $event->type === \Dibi\Event::ROLLBACK || preg_match('/^ROLLBACK\b(?!\s+(?:WORK\s+)?TO\b)/i', $sql);
        if (preg_match('/^SET\s+(?:SESSION\s+)?(?:@@(?:session\.)?)?autocommit\s*=\s*([01])/i', $sql, $match))
        {
            $this->autocommit = $match[1] === '1';
            $begin = !$this->autocommit;
            $commit = $this->autocommit;
        }
        if ($begin || $commit || $rollback)
        {
            if (!class_exists(Cfg::class, false))
                return;
            require_once __DIR__.'/cachestore.class.php';
            if ($commit && $this->pending)
                CacheStore::invalidate(...array_keys($this->pending));
            if ($commit || $rollback)
                $this->pending = [];
            $this->transaction = (bool)$begin || !$this->autocommit;
            CacheStore::transaction($this->connection, $this->transaction);
            return;
        }
        if (!preg_match('/^(?:INSERT|REPLACE|UPDATE|DELETE|TRUNCATE|ALTER|CREATE|DROP|RENAME)\b/i', $sql))
            return;
        if (!$this->prefix || !class_exists(Cfg::class, false))
            return;
        // A comment/article body mentioning a table is data, not a mutation of it.
        $identifiers = preg_replace('~\x27(?:[^\x27\\\\]|\\\\.|\x27\x27)*\x27|"(?:[^"\\\\]|\\\\.|"")*"|/\*.*?\*/|--[^\n]*|\#[^\n]*~s', ' ', $sql);
        preg_match_all('/(?<![a-z0-9_])`?'.preg_quote($this->prefix, '/').'([a-z0-9_]+)`?/i', $identifiers, $matches);
        $scopes = [];
        foreach (array_unique($matches[1]) as $table)
        {
            $table = strtolower($table);
            if ($table === 'guides' && preg_match('/^UPDATE\s+`?'.preg_quote($this->prefix, '/').'guides`?\s+SET\s+`?views`?\s*=\s*`?views`?\s*\+\s*1\s+WHERE\b/i', $sql))
                continue;
            if ($table === 'guides' || $table === 'guide_references')
                $scopes['guides'] = true;
            else if ($table === 'articles')
                $scopes += ['content' => true, 'guides' => true];
            else if ($table === 'config')
                $scopes += ['catalog' => true, 'content' => true, 'guides' => true];
            else if (!preg_match('/^(?:account(?:_|$)|comments(?:_|$)|user_|profiler_|screenshots(?:_|$)|videos(?:_|$)|guides_changelog$|errors$|home_)/', $table))
                $scopes['catalog'] = true;
        }
        if (!$scopes)
            return;
        require_once __DIR__.'/cachestore.class.php';
        if ($this->transaction && !preg_match('/^(?:ALTER|CREATE|DROP|TRUNCATE|RENAME)\b(?!\s+TEMPORARY\b)/i', $sql))
            $this->pending += $scopes;
        else
        {
            CacheStore::invalidate(...array_keys($this->pending + $scopes));
            $this->pending = [];
            $this->transaction = !$this->autocommit;
            CacheStore::transaction($this->connection, $this->transaction);
        }
    }
}
