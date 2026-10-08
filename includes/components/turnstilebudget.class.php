<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Separate from password/contribution work: even invalid CAPTCHA tokens consume verification capacity. */
final class TurnstileBudget
{
    public const int PEER_LIMIT = 20;
    public const int PEER_WINDOW = 5 * MINUTE;
    public const int GLOBAL_LIMIT = 120;
    public const int GLOBAL_WINDOW = MINUTE;
    public const int MAX_PEERS = 4096;
    public const int RECLAIM = 8;
    private const string PEER_PREFIX = 'captcha-ip-';

    public static function reserve() : bool
    {
        if (!filter_var(User::$ip, FILTER_VALIDATE_IP))
            return false;
        $address = inet_pton(User::$ip);
        // A mapped IPv4 peer and its IPv4 spelling must share the same allowance.
        if (strlen($address) === 16 && substr($address, 0, 12) === str_repeat("\0", 10)."\xff\xff")
            $address = substr($address, 12);
        $peer = self::PEER_PREFIX.substr(hash('sha256', $address), 0, 21);
        $started = false;
        $committed = false;
        try
        {
            $db = DB::Aowow();
            $started = true;
            $db->query('START TRANSACTION');
            // This unique row serializes reservations and peer-key admission across workers.
            $db->query('INSERT INTO ::contribution_budget (`owner`, `bucket`, `used`, `expires`) VALUES (0, %s, 0, UNIX_TIMESTAMP() + %i) ON DUPLICATE KEY UPDATE `used` = IF(`expires` <= UNIX_TIMESTAMP(), 0, `used`), `expires` = IF(`expires` <= UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, `expires`)',
                'captcha-global', self::GLOBAL_WINDOW, self::GLOBAL_WINDOW);
            $db->query('UPDATE ::contribution_budget SET `used` = `used` + 1 WHERE `owner` = 0 AND `bucket` = %s AND `used` < %i AND `expires` > UNIX_TIMESTAMP()',
                'captcha-global', self::GLOBAL_LIMIT);
            if ($db->getAffectedRows() !== 1)
                return false;

            // Bounded online cleanup and a hard key cap protect storage even without scheduled pruning.
            $db->query('DELETE FROM ::contribution_budget WHERE `owner` = 0 AND `bucket` LIKE %s AND `expires` > 0 AND `expires` <= UNIX_TIMESTAMP() ORDER BY `bucket` LIMIT %i',
                self::PEER_PREFIX.'%', self::RECLAIM);
            if (!$db->query('SELECT 1 FROM ::contribution_budget WHERE `owner` = 0 AND `bucket` = %s', $peer)->fetchSingle() &&
                (int)$db->query('SELECT COUNT(*) FROM ::contribution_budget WHERE `owner` = 0 AND `bucket` LIKE %s', self::PEER_PREFIX.'%')->fetchSingle() >= self::MAX_PEERS)
                return false;

            $db->query('INSERT INTO ::contribution_budget (`owner`, `bucket`, `used`, `expires`) VALUES (0, %s, 0, UNIX_TIMESTAMP() + %i) ON DUPLICATE KEY UPDATE `used` = IF(`expires` <= UNIX_TIMESTAMP(), 0, `used`), `expires` = IF(`expires` <= UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + %i, `expires`)',
                $peer, self::PEER_WINDOW, self::PEER_WINDOW);
            $db->query('UPDATE ::contribution_budget SET `used` = `used` + 1 WHERE `owner` = 0 AND `bucket` = %s AND `used` < %i AND `expires` > UNIX_TIMESTAMP()',
                $peer, self::PEER_LIMIT);
            if ($db->getAffectedRows() !== 1)
                return false;

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
}
