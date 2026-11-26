<?php
// Actual profile-save handler, synthetic identities/rows; no application database.
namespace Aowow {
    class TextResponse {
        protected array $_get = [], $_post = [];
        protected mixed $result = null;
        protected bool $requiresLogin = false;
    }
    class User { public static int $id = 7; public static function isPremium() : bool { return false; } }
    class DB {
        public static array $profiles = [], $writes = [];
        public static function Aowow() : self { return new self; }
        public function selectCell(string $sql, mixed ...$args) : ?int {
            if (str_contains($sql, 'COUNT(*)'))
                return count(array_filter(self::$profiles, fn($p) => $p['user'] === $args[0] && !$p['deleted'] && $p['custom']));
            $row = self::$profiles[$args[0]] ?? null;
            if (!$row || (isset($args[1]) && ($row['user'] !== $args[1] || $row['deleted'] || !$row['custom'])))
                return null;
            return $args[0];
        }
        public function qry(string $sql, mixed ...$args) : int {
            self::$writes[] = [$sql, $args];
            return 100;
        }
    }
    class EnchantmentList {}
    class ItemList {
        public static bool $available=false;
        public static array $randomIds=[];
        public bool $error;
        public array $json=[1000=>['nsockets'=>0]];
        public function __construct(array $conditions) { $this->error=!self::$available; }
        public function getEntry(int $id) : ?array { return $id===1000 ? ['id'=>$id] : null; }
        public function getRandEnchantForItem(int $id) : array { self::$randomIds[]=$id; return $id===-5 ? ['id'=>$id] : []; }
    }
}
namespace {
    define('AOWOW_REVISION', 77);
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../endpoints/profile/save.php';
}
namespace Aowow {
    class ProfileProbe extends ProfileSaveResponse {
        public function run(int $id, array $inventory=[]) : mixed {
            $this->_get = ['id'=>$id];
            $this->_post = array_fill_keys(['name','level','class','race','gender','nomodel','talenttree1','talenttree2','talenttree3','talentbuild1','talentbuild2','activespec','glyphs1','glyphs2','gearscore','public','description','source','copy'], 0);
            $this->_post += ['icon'=>'', 'inv'=>$inventory];
            $this->generate();
            return $this->result;
        }
        public function needsLogin() : bool { return $this->requiresLogin; }
    }
}
namespace {
    use Aowow\{DB, ItemList, ProfileProbe};
    set_error_handler(function(int $code, string $message) : never { throw new ErrorException($message, 0, $code); });
    $checks=0;
    function check(bool $ok, string $message) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
    function probe() : ProfileProbe { return (new ReflectionClass(ProfileProbe::class))->newInstanceWithoutConstructor(); }
    check(probe()->needsLogin(), 'The real handler opts into the shared login gate');
    foreach ([['user'=>8,'deleted'=>0,'custom'=>1], ['user'=>7,'deleted'=>1,'custom'=>1], ['user'=>7,'deleted'=>0,'custom'=>0], null] as $row) {
        DB::$profiles = $row ? [42=>$row] : []; DB::$writes=[];
        check(probe()->run(42) === -1, 'Foreign, deleted, real or missing targets return failure');
        check(DB::$writes === [], 'Denied target performs no metadata or inventory writes');
    }
    DB::$profiles=[42=>['user'=>7,'deleted'=>0,'custom'=>1]]; DB::$writes=[];
    check(probe()->run(42) === 42 && count(DB::$writes) === 1, 'An owned active custom profile can still be updated');
    check(DB::$writes[0][1][0]['user'] === 7 && DB::$writes[0][1][1] === 42, 'Update retains current owner and the selected profile');
    ItemList::$available=true;
    foreach ([-5,-6] as $suffix) {
        DB::$writes=[];ItemList::$randomIds=[];
        check(probe()->run(42,[[0,1000,$suffix,0,0,0,0,0,0]])===42 && count(DB::$writes)===2,'Owned profiles retain POST inventory writes');
        check(ItemList::$randomIds===[$suffix],'Random-property validation receives the suffix ID rather than the base item ID');
        $saved=DB::$writes[1][1][0];
        check($saved['id']===42 && $saved['item']===1000 && $saved['subitem']===($suffix===-5?-5:0),'Valid suffixes survive and invalid suffixes are cleared');
    }
    ItemList::$available=false;
    DB::$profiles=[];DB::$writes=[];
    check(probe()->run(0) === 100 && count(DB::$writes) === 1, 'Creating a new profile remains supported');
    check(DB::$writes[0][1][0]['user'] === 7, 'New profile belongs to the authenticated user');
    for ($i=1;$i<=10;$i++) DB::$profiles[$i]=['user'=>7,'deleted'=>0,'custom'=>1];
    DB::$writes=[];
    check(probe()->run(0) === -1 && DB::$writes === [], 'The nonpremium profile limit rejects creation without writes');
    echo "PASS: $checks November profile authorization checks\n";
}
