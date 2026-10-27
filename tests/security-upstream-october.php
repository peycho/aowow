<?php

// Real locale/SmartAI/mail, quest, fulltext and profiler code with synthetic inputs.
namespace Aowow {
    class Cfg {
        public static function get(string $key) : mixed { return $key === 'LOCALES' ? 0xFFFF : ($key === 'STATIC_URL' ? '/static' : 0); }
        public static function applyToString(string $value) : string { return $value; }
    }
    class User { public static function isInGroup(int $groups) : bool { return false; } }
    class DB {
        public const string AND = 'AND', OR = 'OR';
        public static array $calls = [];
        public static function Aowow() : self { return new self; }
        public function translate(array $values) : string { return implode(' ', $values); }
        public function selectCol(string $sql, mixed ...$args) : array {
            self::$calls[] = [$sql, $args];
            return $args[0] === -5 ? [900 => 1] : [901 => 1];
        }
    }
}
namespace {
    define('AOWOW_REVISION', 75); define('CLI', true);
    $root = dirname(__DIR__);
    foreach (['defines.php','locale.class.php','type.class.php','utilities.php','components/guidemgr.class.php'] as $path)
        require $root.'/includes/'.$path;
    foreach (['SmartAI','SmartEvent','SmartAction','SmartTarget'] as $class)
        require $root.'/includes/components/SmartAI/'.$class.'.class.php';
    require $root.'/localization/lang.class.php';
    require $root.'/localization/datetime.class.php';
    require $root.'/includes/game/uitext.class.php';
    require $root.'/includes/components/locstring.class.php';
    require $root.'/includes/components/frontend/mail.class.php';
    require $root.'/includes/components/dbtypelist.class.php';
    require $root.'/includes/components/filter.class.php';
    require $root.'/includes/dbtypes/quest.class.php';
    require $root.'/includes/dbtypes/profile.class.php';
}
namespace Aowow {
    class FixtureTarget extends SmartTarget {
        public function __construct() {}
        public function getTalkSource(bool &$playerSrc = false) : int { return 1; }
    }
    class FixtureAI extends SmartAI {
        public bool $hasText = false;
        public function __construct() {}
        public function getTarget(int $id = -1) : ?SmartTarget { return new FixtureTarget; }
        public function getQuote(int $creatureId, int $group, ?string &$npcSrc) : array {
            $npcSrc = 'Fixture NPC';
            return $this->hasText ? [['text'=>'Fixture dialogue']] : [];
        }
        public function addJsGlobals(array $jsg) : void {}
    }
    class FixtureQuest extends QuestList {
        public function __construct() { $this->seed([]); }
        public function seed(array $values) : void {
            $this->curTpl = $values + ['name'=>'Fixture quest','level'=>-1,'flags'=>0];
            foreach (range(1,6) as $i)
                foreach (['reqNpcOrGo','reqNpcOrGoCount','reqItemId','reqItemCount'] as $key)
                    $this->curTpl[$key.$i] = 0;
        }
        public function getField(string $field, bool $localized = false, bool $silent = false) : mixed { return $this->curTpl[$field] ?? 0; }
    }
}
namespace {
    use Aowow\{DB,DBTypeList,FixtureAI,FixtureQuest,Lang,Locale,LocString,Mail,ProfileListFilter,SmartAction};
    set_error_handler(function(int $code,string $message):never { throw new ErrorException($message,0,$code); });
    $checks = 0;
    function check(bool $ok,string $message) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
    foreach ([Locale::EN,Locale::FR,Locale::DE,Locale::CN,Locale::ES,Locale::RU] as $locale) {
        Lang::load($locale);
        $strings = ['name_loc0'=>'Fixture letter'];
        $mail = new Mail(123,new LocString($strings),new LocString($strings));
        (new ReflectionProperty($mail,'sender'))->setValue($mail,' Fixture sender');
        (new ReflectionProperty($mail,'delay'))->setValue($mail,' Fixture delay');
        $header = $mail->renderHeader();
        check(str_contains($header,'href="?mail=123"'),$locale->name.': delivered mail links to its own ID');
        check(str_contains($header,'Fixture sender') && str_contains($header,'Fixture delay'),$locale->name.': sender and delay stay in their slots');
        check(Lang::getRequiredItems(ITEM_CLASS_ARMOR, 1 << 30, false) === Lang::spell('cat', -11, 1, 8), 'Unnamed armor subclasses use the nested fallback in each locale');
        check(Lang::getRequiredItems(ITEM_CLASS_WEAPON, 1 << 30, false) === Lang::spell('cat', -11, 1, 6), 'Unnamed weapon subclasses use the nested fallback in each locale');
        check(is_string(Lang::meta('description', 'profile', ['Fixture', 80, 'Race', 'Class', 'Realm', 'Region'])), 'Profile meta descriptions accept exactly six values');
        $ai = new FixtureAI;
        foreach ([SmartAction::ACTION_SET_CORPSE_DELAY, SmartAction::ACTION_PAUSE_MOVEMENT] as $action) {
            $rendered = [];
            foreach ([0, 3000] as $delay) {
                $params = $action === SmartAction::ACTION_PAUSE_MOVEMENT ? [1, $delay, 0, 0, 0, 0] : [$delay, 0, 0, 0, 0, 0];
                [$text] = (new SmartAction(1, $action, $params, $ai))->process();
                check(!preg_match('/%\d+\$/', $text) && !str_contains($text, '(0)?'), 'SmartAI zero/nonzero time branches resolve without format syntax');
                $rendered[] = $text;
            }
            check($rendered[0] !== $rendered[1], 'Zero delay has distinct instant/indefinite meaning in each locale');
        }
        foreach ([0,133] as $spell) {
            [$body] = (new SmartAction(1,SmartAction::ACTION_REMOVEAURASFROMSPELL,[$spell,0,0,0,0,0],$ai))->process();
            check(str_contains($body,'[spell=133]') === ($spell === 133),$locale->name.': only a specific aura creates a spell link');
            check(!str_contains($body,'[spell=0]'),$locale->name.': all-auras action has no zero-spell link');
        }
        foreach ([false,true] as $hasText) {
            $ai->hasText = $hasText;
            [$body] = (new SmartAction(1,SmartAction::ACTION_TALK,[1,0,0,0,0,0],$ai))->process();
            check(str_contains($body,'[span class=q10]') === !$hasText,$locale->name.': Talk uses actual text availability');
            if ($locale === Locale::DE)
                check(str_contains($body,'für den Auslöser'), 'deDE: target zero selects the invoker branch');
        }
    }
    Lang::load(Locale::EN);
    $quest = new FixtureQuest;
    foreach ([['objectives'=>'Objectives','requestItems'=>'Progress','offerReward'=>'Reward'],['requestItems'=>'Progress','offerReward'=>'Reward'],['offerReward'=>'Reward']] as $texts) {
        $quest->seed($texts);
        $html = $quest->renderTooltip();
        check(str_contains($html,'<br />'.reset($texts)),'Quest tooltip chooses objectives, progress, then reward');
        foreach (array_slice(array_values($texts),1) as $unused)
            check(!str_contains($html,$unused),'Lower-priority quest text is omitted');
    }
    $quest->seed(['requestItems'=>'<unsafe>']);
    check(str_contains($quest->renderTooltip(),'&lt;unsafe&gt;'),'Progress text keeps the existing HTML escaping');

    $prefixes = new ReflectionProperty(DBTypeList::class,'prefixes');
    $prefixes->setValue($quest,['base'=>'q','nml']);
    $condition = new ReflectionMethod(DBTypeList::class,'resolveCondition');
    foreach ([['nml.nName'],['nml.nName','nml.nObjectives','nml.nDetails']] as $fields) {
        $result = $condition->invoke($quest,[$fields,['+fixture*'],'MATCH'],'AND');
        check($result[1] === $fields,'Fulltext lookup retains every requested indexed column');
    }
    $result = $condition->invoke($quest,['((minGold + maxGold) / 2)',0,'>'],'AND');
    check($result[1] === '((q.minGold + q.maxGold) / 2)','Formula callbacks still prefix individual scalar columns');
    $result = $condition->invoke($quest,[['nml.nName','bad;column'],['+fixture*'],'MATCH'],'AND');
    check($result[1] === ['nml.nName'],'Malformed fulltext column names are filtered');
    check($condition->invoke($quest,[['bad;column'],['+fixture*'],'MATCH'],'AND') === null,'Entirely invalid fulltext columns omit the condition');

    foreach ([false,true] as $local) foreach ([80=>900,81=>901] as $criterion=>$spell) {
        DB::$calls = [];
        $filter = new ProfileListFilter('cr='.$criterion.';crs=0;crv='.$spell);
        $filter->useLocalList = $local;
        $conditions = $filter->getConditions();
        $join = reset($filter->extraOpts)['j'][0];
        check(str_contains($join,$local ? '::profiler_completion_spells' : 'character_spell'),'Collectible filter uses the selected profile backend');
        check(str_contains($join,$local ? '.`spellId` = '.$spell : '.`spell` = '.$spell),'Collectible ID is bound to its join');
        check($local || (str_contains($join,'`active` = 1') && str_contains($join,'`disabled` = 0')),'Remote spell lookup excludes inactive and disabled spells');
        check($conditions !== [] && count(DB::$calls) === 1,'Collectible category is checked once before producing a condition');
    }
    $callback = new ReflectionMethod(ProfileListFilter::class,'cbCollectibleSpell');
    $filter = new ProfileListFilter('');
    foreach (['not-an-id','1;DROP TABLE','1e3','','0','-1','900.5','4294967296'] as $id) {
        DB::$calls = [];
        check($callback->invoke($filter,80,0,$id,-5) === null && DB::$calls === [],'Malformed collectible IDs cannot create joins or queries');
    }
    check($callback->invoke($filter,80,0,'901',-5) === [0],'Pet IDs cannot match a mount criterion');
    check($filter->extraOpts === [],'Rejected collectible IDs create no join');
    echo "PASS: $checks October upstream locale, quest, search and profiler checks\n";
}
