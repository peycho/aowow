<?php
// Actual item renderer and locale data; synthetic item rows, no game database.
namespace Aowow {
    class Cfg {
        public static function get(string $key):mixed { return $key==='LOCALES'?0xFFFF:0; }
        public static function applyToString(string $text):string { return $text; }
    }
    class User { public static function isInGroup(int $mask):bool { return false; } }
    class DB { public static function Aowow():self { throw new \RuntimeException('Unexpected database access'); } }
}
namespace {
    define('AOWOW_REVISION',78);define('CLI',true);
    $root=dirname(__DIR__);
    foreach(['defines.php','locale.class.php','type.class.php','utilities.php','components/guidemgr.class.php','components/dbtypelist.class.php','components/filter.class.php','game/chrstatistics.php','game/uitext.class.php','dbtypes/item.class.php'] as $file)require $root.'/includes/'.$file;
    foreach(['SmartAI','SmartEvent','SmartAction','SmartTarget'] as $class)require $root.'/includes/components/SmartAI/'.$class.'.class.php';
    require $root.'/includes/game/chrrace.class.php';
    require $root.'/includes/game/chrclass.class.php';
    require $root.'/localization/lang.class.php';
    require $root.'/localization/datetime.class.php';
}
namespace Aowow {
    class FixtureLevelItem extends ItemList {
        public function __construct(int $required,bool $rating,bool $heirloom) {
            $schema=file_get_contents(dirname(__DIR__).'/setup/sql/01-db_structure.sql');
            foreach(['items','item_stats'] as $table) {
                preg_match('/^CREATE TABLE `aowow_'.$table.'` .*?^\).*?;/ms',$schema,$ddl);
                preg_match_all('/^  `([^`]+)`/m',$ddl[0],$columns);
                foreach($columns[1] as $column)$this->curTpl[$column]=0;
            }
            $this->id=1000;$this->error=false;
            $this->curTpl=array_replace($this->curTpl,['id'=>1000,'name_loc0'=>'Fixture item','class'=>ITEM_CLASS_ARMOR,'classBak'=>ITEM_CLASS_ARMOR,'subClassBak'=>0,'requiredLevel'=>$required,'quality'=>ITEM_QUALITY_EPIC,'tplDmgMin1'=>0,'tplDmgMax1'=>0,'tplArmor'=>0,'tplBlock'=>0]);
            for($i=1;$i<=10;$i++)$this->curTpl+=['statType'.$i=>0,'statValue'.$i=>0];
            if($rating){$this->curTpl['statType1']=ITEM_MOD_CRIT_RATING;$this->curTpl['statValue1']=100;}
            if($heirloom){
                $this->curTpl['quality']=ITEM_QUALITY_HEIRLOOM;$this->curTpl['flags']=ITEM_FLAG_IS_BOUND_TO_ACCOUNT;
                $this->curTpl['scalingStatDistribution']=1;$this->curTpl['scalingStatValue']=1032;
                (new \ReflectionProperty(ItemList::class,'ssd'))->setValue($this,[1000=>['maxLevel'=>80]]);
            }
        }
        public function getExtendedCost(?array $filter=[],?array &$reqRating=null):array { $reqRating=null;return []; }
    }
}
namespace {
    use Aowow\{FixtureLevelItem,Lang,Locale};
    set_error_handler(function(int $code,string $message):never {throw new ErrorException($message,0,$code);});
    $checks=0;$fixtures=[];
    foreach([Locale::EN,Locale::FR,Locale::DE,Locale::CN,Locale::ES,Locale::RU] as $locale){
        Lang::load($locale);
        foreach([[80,true,false,[80,80,80]],[70,true,false,[70,80,70]],[70,false,false,[1,1,1]],[0,true,true,[1,80,80]],[0,false,false,[1,1,1]]] as [$required,$rating,$heirloom,$expected]){
            $item=new FixtureLevelItem($required,$rating,$heirloom);$html=$item->renderTooltip(true);
            preg_match('/<!--\?1000:(\d+):(\d+):(\d+):(\d+):(\d+)-->$/',$html,$match);
            if(!$match||array_map('intval',array_slice($match,1,3))!==$expected)throw new RuntimeException('Wrong tooltip level bounds for '.$locale->name.' required='.$required.' rating='.(int)$rating.' heirloom='.(int)$heirloom.': '.json_encode($match));
            ++$checks;
            if($locale===Locale::EN)$fixtures[]=['tooltip'=>$html,'min'=>$expected[0],'max'=>$expected[1],'current'=>$expected[2],'slider'=>$expected[0]<$expected[1]];
        }
    }
    if(in_array('--fixtures',$argv,true)){echo json_encode($fixtures,JSON_THROW_ON_ERROR);exit;}
    echo "PASS: $checks item tooltip level-range checks\n";
}
