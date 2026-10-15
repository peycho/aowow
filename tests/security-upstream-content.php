<?php
// Actual spell-lock and zone-fishing tab helpers with synthetic DB/loot responses.
namespace Aowow {
    class Cfg { public static function get(string $key) : int { return 0; } }
    class TemplateResponse {
        protected const int TAB_DATABASE=0;
        public array $globals=[];
        protected function extendGlobalData(array $data) : void { $this->globals[]=$data; }
    }
    interface ICache {}
    trait TrDetailPage {}
    trait TrCache {}
    class Lang {
        public static function zone(string $key) : string { return 'Fishing skill: %s'; }
        public static function formatSkillBreakpoints(array $points,int $format) : string { return (string)$points[0]; }
        public const int FMT_HTML=1;
    }
    class Game { public static function getBreakpointsForSkill(int $skill,int $value) : array { return [$value]; } }
    class DB {
        public static array $locks=[], $skills=[], $calls=[];
        public static function Aowow() : self { return new self; }
        public static function World() : self { return new self; }
        public function selectCol(string $sql,mixed ...$args) : array { self::$calls[]=$args; return array_shift(self::$locks); }
        public function selectCell(string $sql,int $id) : ?int { return self::$skills[$id]??null; }
    }
    class SpellList { public function getField(string $field) : int { return $field==='effect1Id'?SPELL_EFFECT_OPEN_LOCK:633; } }
    class GameObjectList {
        public bool $error=true;
        public static array $conditions=[];
        public function __construct(array $conditions) { self::$conditions=$conditions; }
    }
    class ItemList {
        public bool $error=true;
        public static string $brickFile='item';
        public static array $conditions=[];
        public function __construct(array $conditions) { self::$conditions=$conditions; }
    }
    class LootByContainer {
        public static array $rows=[], $calls=[];
        public array $jsGlobals, $extraCols;
        public function __construct(string $template,private int $id) {
            self::$calls[]=[$template,$id];
            $this->jsGlobals=[3=>[$id=>['name'=>'Synthetic fish']]];
            $this->extraCols=[new JsExpression('Listview.extraCols.percent')];
        }
        public function getItems() : array { return array_column(self::$rows[$this->id]??[],'id'); }
        public function formatListview() : array { return self::$rows[$this->id]??[]; }
    }
}
namespace {
    use Aowow\{DB,GameObjectList,ItemList,JsExpression,Loot,LootByContainer,SpellBaseResponse,SpellList,Util,ZoneBaseResponse};
    define('AOWOW_REVISION',74); define('CLI',true);
    $root=dirname(__DIR__);
    require $root.'/includes/defines.php'; require $root.'/includes/type.class.php'; require $root.'/includes/utilities.php';
    require $root.'/includes/components/jsexpression.class.php';
    require $root.'/includes/components/frontend/listview.class.php';
    require $root.'/includes/game/loot/loot.class.php';
    require $root.'/endpoints/spell/spell.php'; require $root.'/endpoints/zone/zone.php';
    set_error_handler(function(int $level,string $message):never { throw new ErrorException($message,0,$level); });
    $checks=0;
    function check(bool $ok,string $label):void { global $checks; ++$checks; if(!$ok)throw new RuntimeException($label); }
    $page=(new ReflectionClass(SpellBaseResponse::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($page,'subject'))->setValue($page,new SpellList);
    $page->typeId=1804;
    DB::$locks=[[10,20],[20,30]];
    (new ReflectionMethod($page,'tabUnlocks'))->invoke($page);
    check(array_values(GameObjectList::$conditions[0][1])===[10,20,30],'overlapping numeric result keys retain all distinct spell and skill locks');
    check(GameObjectList::$conditions===ItemList::$conditions,'object and item tabs receive the same merged locks');
    check(DB::$calls[0][0]===LOCK_TYPE_SPELL && DB::$calls[1][0]===LOCK_TYPE_SKILL,'spell and skill queries retain distinct parameterized types');
    $zone=(new ReflectionClass(ZoneBaseResponse::class))->newInstanceWithoutConstructor(); $zone->typeId=2;
    $fishing=new ReflectionMethod($zone,'tabFishing');
    $row=['id'=>1,'name'=>'$(globalThis.fishMarker=1)</script>','percent'=>100];
    foreach ([[[2=>[$row],1=>[['id'=>9]]],1,[2]],[[1=>[$row]],1,[2,1]],[[],1,[2,1]],[[],0,[2]]] as [$rows,$parent,$calls]) {
        LootByContainer::$rows=$rows; LootByContainer::$calls=[]; $zone->globals=[]; DB::$skills=[1=>225];
        $tab=$fishing->invoke($zone,$parent);
        check(array_column(LootByContainer::$calls,1)===$calls,'subzone loot takes priority and parent lookup occurs only when needed');
        if(!$rows) { check($tab===null && !$zone->globals,'empty loot has no tab or globals'); continue; }
        $options=$tab->jsonSerialize();
        check($options['data']===[$row],'selected fishing loot rows preserved');
        check($options['name']->expression==='LANG.tab_fishing' && $options['computeDataFunc']->expression==='Listview.funcBox.initLootTable','fishing label and callback stay explicit executable expressions');
        check(count($options['extraCols'])===1,'duplicate percent columns removed');
        check(str_contains($options['note'],'225'),'parent fishing skill supplies the note when subzone skill is absent');
        check(count($zone->globals)===1,'only the selected loot globals are extended');
        check(!str_contains(Util::toJavaScript($options),'</script>'),'loot item text cannot escape an inline script');
    }
    foreach ([0,1,2,3] as $sockets)
        check(Util::getEquipmentScore(1,ITEM_QUALITY_RARE,INVTYPE_HEAD,$sockets)>=0,'low-level custom equipment score stays nonnegative');
    check(Util::getEquipmentScore(200,ITEM_QUALITY_EPIC,INVTYPE_HEAD)===166.6667,'ordinary item score remains unchanged');
    echo "PASS: $checks upstream lock/fishing/serialization/gearscore checks\n";
}
