<?php

// Actual GuideList lookups and list output against the real schema in an
// isolated disposable database. Never load application credentials or world data.
namespace Aowow {
    class Cfg {
        public static function get(string $key) : mixed { return $key === 'LOCALES' ? 0x15D : 0; }
    }
    class User {
        public static int $id = 0;
        public static int $groups = 0;
        public static function isInGroup(int $mask) : bool { return (bool)(self::$groups & $mask); }
    }
    class Lang {
        public static function getLocale() : Locale { return Locale::EN; }
        public static function game(string $key) : string { return $key === 'guide' ? 'Guide' : $key; }
    }
}
namespace {
    use Aowow\{DB, DibiConnection, DBTypeList, GuideList, GuideMgr, User};
    if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_seo') {
        fwrite(STDERR, "Use only the disposable aowow_security_test_seo database.\n"); exit(1);
    }
    define('AOWOW_REVISION', 75); define('CLI', true); define('CLI_HAS_E', false); define('OS_WIN', false);
    $root = dirname(__DIR__);
    require $root.'/includes/libs/autoload.php';
    foreach (['defines.php', 'database.php', 'locale.class.php', 'type.class.php', 'utilities.php',
        'components/locstring.class.php', 'components/dbtypelist.class.php', 'components/guidemgr.class.php',
        'dbtypes/guide.class.php'] as $file) require $root.'/includes/'.$file;
    $db = new DibiConnection([
        'driver' => 'mysqli', 'host' => getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306), 'username' => 'root', 'password' => '',
        'database' => 'aowow_security_test_seo', 'charset' => 'utf8mb4', 'substitutes' => ['' => 'aowow_']
    ]);
    (new ReflectionProperty(DB::class, 'interfaceCache'))->setValue(null, [DB_AOWOW => $db]);
    (new ReflectionProperty(DB::class, 'interfaceTimes'))->setValue(null, [DB_AOWOW => [time() + 86400, 86400]]);
    $db->query('DROP TABLE IF EXISTS ::guides');
    preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`aowow_guides` \(.*?\) ENGINE=.*?;/s', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $schema);
    if (!$schema) throw new RuntimeException('Real guides schema missing');
    $db->nativeQuery($schema[0]);
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    try {
        foreach ([GuideMgr::STATUS_DRAFT, GuideMgr::STATUS_REVIEW, GuideMgr::STATUS_APPROVED, GuideMgr::STATUS_REJECTED, GuideMgr::STATUS_ARCHIVED] as $status) {
            foreach ([0, 2] as $revision) {
                $db->query('DELETE FROM ::guides');
                $db->query('INSERT INTO ::guides %v', [
                    'id' => 1, 'userId' => 1, 'status' => $status, 'rev' => $revision,
                    'title' => $status === GuideMgr::STATUS_APPROVED ? 'Published title' : 'SECRET_PENDING_TITLE',
                    'name' => 'Fixture name', 'description' => 'SECRET_PENDING_DESCRIPTION', 'url' => 'Guide-Slug',
                    'category' => 2, 'date' => 1700000000
                ]);
                $public = $status === GuideMgr::STATUS_APPROVED || $revision > 0;
                $name = GuideList::getName(1);
                check(($name !== null) === $public, 'real SQL name lookup hides unpublished guides');
                if ($name !== null)
                    check((string)$name === ($status === GuideMgr::STATUS_APPROVED ? 'Published title' : 'Guide #1'), 'real SQL name lookup hides pending metadata');
                $row = $db->query('SELECT * FROM ::guides WHERE id = 1')->fetch()->toArray();
                foreach (['guest', 'owner', 'staff', 'other'] as $actor) {
                    User::$id = match ($actor) { 'owner' => 1, 'staff' => 2, 'other' => 3, default => 0 };
                    User::$groups = $actor === 'staff' ? U_GROUP_ADMIN : 0;
                    $guide = (new ReflectionClass(GuideList::class))->newInstanceWithoutConstructor();
                    (new ReflectionProperty(DBTypeList::class, 'templates'))->setValue($guide, [1 => $row]);
                    (new ReflectionProperty(DBTypeList::class, 'curTpl'))->setValue($guide, $row);
                    $guide->id = 1;
                    check($guide->canBeViewed() === $public, 'actual publication predicate retains prior approved revisions');
                    check($guide->userCanView() === in_array($actor, ['owner', 'staff'], true), 'actual owner/staff predicate excludes guests and unrelated accounts');
                    $data = $guide->getListviewData()[1];
                    check($data['url'] === '?guide=guide-slug', 'list links use shared normalized guide slug');
                    if ($revision > 0 && $status !== GuideMgr::STATUS_APPROVED && in_array($actor, ['guest', 'other'], true))
                        check($data['title'] === 'Guide #1' && $data['description'] === '', 'public lists never advertise pending free-text metadata');
                    else
                        check($data['title'] === $row['title'], 'approved names and authorized editorial list data are retained');
                }
            }
        }
        check(GuideList::getName(9999) === null, 'missing guide name remains unavailable');
        echo "PASS: $checks real SQL guide name, publication and list visibility checks\n";
    }
    finally { $db->query('DROP TABLE IF EXISTS ::guides'); }
}
