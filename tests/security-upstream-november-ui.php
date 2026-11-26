<?php
// Render the real quest template without executing JavaScript or loading game data.
namespace Aowow {
    class Lang {
        public static function main(string $key, mixed ...$args) : string { return $key === 'colon' ? ': ' : $key; }
        public static function quest(string $key, mixed ...$args) : string { return $key; }
        public static function nf(int $number) : string { return number_format($number); }
    }
}
namespace {
    define('AOWOW_REVISION',77);
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../includes/utilities.php';
    class QuestPage {
        public array $rewards = [[], [], [], true];
        public array $gains = [true, [], null, 0, null, 5];
        public array $rewardsScaler = [];
        public function __get(string $name) : mixed { return $name === 'h1' ? 'Fixture quest' : null; }
        private function brick(string $name, array $args = []) : void { if ($name === 'rewards') echo $args['extra'] ?? ''; }
        private function brickIf(mixed ...$args) : void {}
        private function json(string $name, mixed ...$args) : string { return json_encode($this->$name,JSON_HEX_TAG|JSON_THROW_ON_ERROR); }
        public function render() : string { ob_start();include __DIR__.'/../template/pages/quest.tpl.php';return ob_get_clean(); }
    }
    set_error_handler(function(int $code,string $message):never { throw new ErrorException($message,0,$code); });
    $checks=0;
    function check(bool $ok,string $message):void {global $checks;++$checks;if(!$ok)throw new RuntimeException($message);}
    $page=new QuestPage;
    $page->rewardsScaler=['maxLevel'=>20,'coin'=>['levels'=>[20=>12345]],'xp'=>['levels'=>[20=>1200]]];
    $html=$page->render();
    check(str_contains($html,'id="quest-reward-xp">1,200 experience</div>'),'XP is readable in server HTML before any JavaScript');
    check(str_contains($html,'id="quest-reward-coin"><span class="moneygold">1</span>'),'Money is readable in server HTML before any JavaScript');
    check(str_contains($html,'moneyarena') && !str_contains($html,'moneyalliance'),'Arena-only gains render without missing honor fields');
    $page->rewardsScaler=['maxLevel'=>80,'coin'=>['levels'=>[80=>10000],'rewardAtCap'=>25000],'xp'=>['levels'=>[80=>0]]];
    $html=$page->render();
    check(str_contains($html,'id="quest-reward-coin"><span class="moneygold">2</span> <span class="moneysilver">50</span>'),'Default level-80 HTML agrees with cap compensation');
    check(str_contains($html,'id="quest-reward-xp">0 experience</div>'),'Zero rewards remain a valid readable fallback');
    echo "PASS: $checks November quest server-rendering checks\n";
}
