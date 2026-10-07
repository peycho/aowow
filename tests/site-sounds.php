<?php
// Execute real sound routes, page helpers, search, metadata and dialogue with synthetic dependencies.
namespace Aowow\Template { class PageTemplate {} }
namespace Aowow {
    class Cfg {
        public static bool $sounds = true;
        public static function get(string $key) : mixed { return $key === 'SOUNDS_ENABLE' ? self::$sounds : 'https://example.test'; }
    }
    class DisabledSound extends \RuntimeException {}
    class QueryReached extends \RuntimeException {}
    class TemplateResponse {
        protected const int TAB_DATABASE = 0;
        protected array $category = [];
        public function __construct(string $raw = '') {}
        protected function generateError() : never { throw new DisabledSound; }
        protected function getCategoryFromUrl(string $raw) : void {}
    }
    trait TrDetailPage { public function getCacheKeyComponents() : array { return [0, 0, 0, '']; } }
    trait TrListPage { public function getCacheKeyComponents() : array { return [0, 0, 0, '']; } }
    trait spawnHelper {}
    class Filter {}
    class DBTypeList {
        public static int $calls = 0;
        public bool $error = true;
        public function __construct(array $conditions = [], array $miscData = []) { self::$calls++; }
        public function &iterate() : \Generator { if (false) { $row = []; yield $row; } }
    }
    class Subject { public function getField(string $name, bool $localized = false) : int { return $name === 'class' ? ITEM_CLASS_WEAPON : 0; } }
    class ItemList extends Subject {}
    class SpellList extends Subject {}
    class CreatureList extends Subject {}
    class EmoteList extends Subject {}
    class ZoneList { public bool $error = true; }
    class Lang {
        public const int FMT_HTML = 1, FMT_MARKUP = 2;
        public static function getLocale() : Locale { return Locale::EN; }
        public static function npc(string $key, int $id) : string { return 'says'; }
        public static function main(string $key) : string { return ': '; }
    }
    class User { public static function isInGroup(int $group) : bool { return false; } }
    class UIText { public static function format(string $text, int $mode) : string { return $text; } }
    class DB {
        public const int AND = 1;
        public static bool $quotes = false;
        public static function Aowow() : self { return new self; }
        public static function World() : self { return new self; }
        public function __call(string $method, array $args) : mixed {
            if (self::$quotes && $method === 'selectAssoc')
                return [[['soundId'=>42, 'talkType'=>0, 'text_loc0'=>'Fixture dialogue', 'lang'=>0, 'range'=>0]]];
            throw new QueryReached;
        }
    }
    class SmartAI { public const int SRC_TYPE_CREATURE = 0; public static function getSoundsPlayedForOwner(int $id, int $type) : array { return []; } }
}
namespace {
    use Aowow\{Cfg, DB, DBTypeList, DisabledSound, QueryReached, SoundList, Type, Markup, Game};
    define('AOWOW_REVISION', 69); define('CLI', in_array('--cli', $argv, true));
    $root = dirname(__DIR__);
    require $root.'/includes/defines.php';
    require $root.'/includes/locale.class.php';
    require $root.'/includes/type.class.php';
    require $root.'/includes/utilities.php';
    require $root.'/includes/components/response/baseresponse.class.php';
    require $root.'/includes/components/frontend/markup.class.php';
    require $root.'/includes/components/search.class.php';
    require $root.'/includes/components/sitemap.class.php';
    require $root.'/includes/dbtypes/sound.class.php';
    require $root.'/includes/game/misc.php';
    foreach (['item','spell','npc','zone','race','emote','sound','sounds'] as $route)
        require $root.'/endpoints/'.$route.'/'.$route.'.php';
    require $root.'/endpoints/sound/sound_playlist.php';
    set_error_handler(function(int $level, string $message) : never { throw new ErrorException($message, 0, $level); });
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    Cfg::$sounds = false;
    if (CLI) {
        new SoundList([['id',42]]);
        check(DBTypeList::$calls === 1, 'Disabled web sound setting does not gate CLI metadata loading');
        check(Type::newList(Type::SOUND, [['id',42]]) instanceof SoundList && DBTypeList::$calls === 2, 'CLI generic sound loading remains available');
        echo "PASS: $checks CLI sound metadata compatibility checks\n";
        exit;
    }

    foreach (['SoundBaseResponse','SoundsBaseResponse','SoundPlaylistResponse'] as $class) {
        $class = 'Aowow\\'.$class;
        try { new $class('42'); check(false, 'Disabled route must reject direct access'); }
        catch (DisabledSound) { check(true, 'Disabled route rejects before queries'); }
    }
    check((new SoundList([['id',42]]))->error && DBTypeList::$calls === 0, 'Disabled SoundList avoids parent metadata queries');
    check(SoundList::getName(42) === null, 'Disabled sound name lookup avoids database');
    check(Type::newList(Type::SOUND, [['id',42]]) === null, 'Generic type loader cannot fetch disabled sounds');
    foreach (['Item'=>Aowow\ItemList::class,'Spell'=>Aowow\SpellList::class,'Npc'=>Aowow\CreatureList::class,'Race'=>null,'Emote'=>Aowow\EmoteList::class,'Zone'=>null] as $name=>$subject) {
        $page = (new ReflectionClass('Aowow\\'.$name.'BaseResponse'))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($page, $name === 'Zone' ? 'addSounds' : 'tabSounds');
        $args = $name === 'Zone' ? [new Aowow\ZoneList] : [];
        check($method->invokeArgs($page,$args) === null, "$name disabled helper exits without accessing data");
        if ($subject) (new ReflectionProperty($page, 'subject'))->setValue($page, new $subject);
        Cfg::$sounds = true;
        try { $method->invokeArgs($page,$args); check(false, "$name enabled helper must reach metadata query"); }
        catch (QueryReached) { check(true, "$name enabled helper still loads sounds"); }
        Cfg::$sounds = false;
    }
    $search = (new ReflectionClass(Aowow\Search::class))->newInstanceWithoutConstructor();
    check((new ReflectionMethod($search,'_searchSound'))->invoke($search) === null, 'Explicit sound search performs no sound lookup');
    check(Aowow\Sitemap::generate('sound',1) === null, 'Disabled sound sitemap exits before queries');
    $markup = '[sound=42][item=7]';
    check(!isset(Markup::parseTags('[SOUND=42]')[Type::SOUND]), 'Disabled case-insensitive sound tag avoids metadata lookup');
    $stubs = Markup::parseTags($markup);
    check(!isset($stubs[Type::SOUND]) && $stubs[Type::ITEM][7] === '7', 'Inline metadata excludes sounds and preserves other tags');
    Cfg::$sounds = true;
    check(isset(Markup::parseTags($markup)[Type::SOUND][42]), 'Enabled inline sound metadata is preserved');
    new SoundList([['id',42]]);
    check(DBTypeList::$calls === 1, 'Enabled SoundList retains parent lookup');
    DB::$quotes = true;
    foreach ([false,true] as $enabled) {
        Cfg::$sounds = $enabled;
        [$quotes,$count,$soundIds] = Game::getQuotesForCreature(7,true,'Fixture NPC');
        check($count === 1 && str_contains($quotes[0][0]['text'],'Fixture dialogue'), 'Dialogue text survives either sound setting');
        check($soundIds === ($enabled ? [42] : []), 'Only enabled dialogue collects sound IDs');
    }
    echo "PASS: $checks sound route/query/metadata/dialogue checks\n";
}
