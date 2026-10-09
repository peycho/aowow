<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Anonymous feedback has its own request and permanent storage allowances. */
final class FeedbackBudget
{
    public const int PEER_LIMIT = 10;
    public const int PEER_WINDOW = HOUR;
    public const int GLOBAL_LIMIT = 1000;
    public const int GLOBAL_WINDOW = DAY;
    public const int MAX_PEERS = 4096;
    public const int RECLAIM = 8;
    public const int RECORD_LIMIT = 10000;
    public const int BYTE_LIMIT = 67108864;
    public const int ROW_OVERHEAD = 1024;
    private const string PEER_PREFIX = 'feedback-ip-';
    private static bool $blocked = false;

    public static function blocked() : bool { return self::$blocked; }

    /** Commit work admission before CAPTCHA; failures and duplicates consume attempts. */
    public static function reserve() : bool
    {
        self::$blocked = false;
        if (!filter_var(User::$ip, FILTER_VALIDATE_IP))
            return false;
        $address = inet_pton(User::$ip);
        if (strlen($address) === 16 && substr($address, 0, 12) === str_repeat("\0", 10)."\xff\xff")
            $address = substr($address, 12);
        $peer = self::PEER_PREFIX.substr(hash('sha256', $address), 0, 20);
        $started = $committed = false;
        try
        {
            $db = DB::Aowow();
            $started = true;
            $db->query('START TRANSACTION');
            // The global row also serializes admission of new, bounded peer keys.
            $db->query('INSERT INTO ::contribution_budget (`owner`, `bucket`, `used`, `expires`) VALUES (0, %s, 0, UNIX_TIMESTAMP() + %i) ON DUPLICATE KEY UPDATE `used` = IF(`expires` <= UNIX_TIMESTAMP(), 0, `used`), `expires` = IF(`expires` <= UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, `expires`)',
                'feedback-global', self::GLOBAL_WINDOW, self::GLOBAL_WINDOW);
            $db->query('UPDATE ::contribution_budget SET `used` = `used` + 1 WHERE `owner` = 0 AND `bucket` = %s AND `used` < %i AND `expires` > UNIX_TIMESTAMP()', 'feedback-global', self::GLOBAL_LIMIT);
            if ($db->getAffectedRows() !== 1)
            {
                self::$blocked = true;
                return false;
            }
            $db->query('DELETE FROM ::contribution_budget WHERE `owner` = 0 AND `bucket` LIKE %s AND `expires` > 0 AND `expires` <= UNIX_TIMESTAMP() ORDER BY `bucket` LIMIT %i', self::PEER_PREFIX.'%', self::RECLAIM);
            if (!$db->query('SELECT 1 FROM ::contribution_budget WHERE `owner` = 0 AND `bucket` = %s', $peer)->fetchSingle() &&
                (int)$db->query('SELECT COUNT(*) FROM ::contribution_budget WHERE `owner` = 0 AND `bucket` LIKE %s', self::PEER_PREFIX.'%')->fetchSingle() >= self::MAX_PEERS)
            {
                self::$blocked = true;
                return false;
            }
            $db->query('INSERT INTO ::contribution_budget (`owner`, `bucket`, `used`, `expires`) VALUES (0, %s, 0, UNIX_TIMESTAMP() + %i) ON DUPLICATE KEY UPDATE `used` = IF(`expires` <= UNIX_TIMESTAMP(), 0, `used`), `expires` = IF(`expires` <= UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, `expires`)', $peer, self::PEER_WINDOW, self::PEER_WINDOW);
            $db->query('UPDATE ::contribution_budget SET `used` = `used` + 1 WHERE `owner` = 0 AND `bucket` = %s AND `used` < %i AND `expires` > UNIX_TIMESTAMP()', $peer, self::PEER_LIMIT);
            if ($db->getAffectedRows() !== 1)
            {
                self::$blocked = true;
                return false;
            }
            $db->query('COMMIT');
            $committed = true;
            return true;
        }
        catch (\Throwable) { return false; }
        finally
        {
            if ($started && !$committed)
                try { $db->query('ROLLBACK'); } catch (\Throwable) { }
        }
    }

    /** Persist the historical baseline once, independently of rejected submissions. */
    public static function prepareStorage(int $bytes) : bool
    {
        self::$blocked = false;
        $started = $committed = false;
        try
        {
            $db = DB::Aowow();
            $read = static fn() => $db->query("SELECT `bucket`, `used` FROM ::contribution_budget WHERE `owner` = 0 AND `bucket` IN ('feedback-stored', 'feedback-bytes') AND `expires` = 0")->fetchPairs();
            $stored = $read();
            if (!$stored)
            {
                $started = true;
                $db->query('START TRANSACTION');
                self::lockStorage($db);
                $stored = $read();
                if (count($stored) !== 2)
                    return false;
                $db->query('COMMIT');
                $committed = true;
            }
            // A partially missing permanent ledger must not silently reset the old charges.
            if (count($stored) !== 2)
                return false;
            self::$blocked = (int)$stored['feedback-stored'] >= self::RECORD_LIMIT ||
                (int)$stored['feedback-bytes'] > self::BYTE_LIMIT - $bytes - self::ROW_OVERHEAD;
            return !self::$blocked;
        }
        catch (\Throwable) { return false; }
        finally
        {
            if ($started && !$committed)
                try { $db->query('ROLLBACK'); } catch (\Throwable) { }
        }
    }

    /** Storage mutex for baseline/report transactions; never held during network work. */
    public static function lockStorage(DibiConnection $db) : void
    {
        $db->query("INSERT INTO ::contribution_budget (`owner`, `bucket`, `used`, `expires`) VALUES (0, 'feedback-stored', 0, 0) ON DUPLICATE KEY UPDATE `used` = `used`");
        if ($db->getAffectedRows() === 1)
        {
            // Seed once from existing records, including closed feedback, without changing them.
            $existing = $db->query('SELECT COUNT(*) AS records, COALESCE(SUM(%i + OCTET_LENGTH(`description`) + OCTET_LENGTH(`userAgent`) + OCTET_LENGTH(`appName`) + OCTET_LENGTH(`url`) + OCTET_LENGTH(COALESCE(`relatedUrl`, \'\')) + OCTET_LENGTH(COALESCE(`email`, \'\')) + OCTET_LENGTH(`ip`)), 0) AS bytes FROM ::reports WHERE `mode` = 0', self::ROW_OVERHEAD)->fetch();
            $db->query("UPDATE ::contribution_budget SET `used` = %i WHERE `owner` = 0 AND `bucket` = 'feedback-stored'", $existing->records);
            $db->query("INSERT INTO ::contribution_budget (`owner`, `bucket`, `used`, `expires`) VALUES (0, 'feedback-bytes', %i, 0)", $existing->bytes);
        }
    }

    /** Charge in the same transaction as the insert; permanent counters are never pruned/refunded. */
    public static function charge(DibiConnection $db, int $bytes) : bool
    {
        $db->query("UPDATE ::contribution_budget SET `used` = `used` + 1 WHERE `owner` = 0 AND `bucket` = 'feedback-stored' AND `expires` = 0 AND `used` < %i", self::RECORD_LIMIT);
        if ($db->getAffectedRows() !== 1)
            return false;
        $db->query("UPDATE ::contribution_budget SET `used` = `used` + %i WHERE `owner` = 0 AND `bucket` = 'feedback-bytes' AND `expires` = 0 AND `used` <= %i", $bytes + self::ROW_OVERHEAD, self::BYTE_LIMIT - $bytes - self::ROW_OVERHEAD);
        return $db->getAffectedRows() === 1;
    }
}
