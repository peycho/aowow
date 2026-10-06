<?php

// Execute the actual item loot-tab helper and frontend serializers with synthetic loot data.
namespace Aowow {
    class Cfg { public static function get(string $key) : int { return 0; } }
    class TemplateResponse {
        protected const int TAB_DATABASE = 0;
        public array $globals = [];
        protected function extendGlobalData(array $data) : void { $this->globals[] = $data; }
    }
    interface ICache {}
    trait TrDetailPage {}
    trait TrCache {}
    class ItemList { public static string $brickFile = 'item'; }
    class LootByContainer {
        public static array $calls = [], $rows = [];
        public static bool $available = true;
        public array $jsGlobals = [3 => [22449 => ['name' => 'Synthetic enchanting material']]];
        public array $extraCols;
        public function __construct(string $template, int $id) {
            self::$calls[] = [$template, $id];
            $this->extraCols = [new JsExpression('Listview.extraCols.percent'), new JsExpression('Listview.extraCols.count')];
        }
        public function formatListview() : bool { return self::$available; }
        public function getResult() : array { return self::$rows; }
    }
}

namespace {
    use Aowow\{ItemBaseResponse, JsExpression, Listview, Loot, LootByContainer, Tabs};
    define('AOWOW_REVISION', 69);
    define('CLI', true);
    $root = dirname(__DIR__);
    require $root.'/includes/defines.php';
    require $root.'/includes/type.class.php';
    require $root.'/includes/utilities.php';
    require $root.'/includes/components/jsexpression.class.php';
    require $root.'/includes/components/frontend/listview.class.php';
    require $root.'/includes/components/frontend/tabs.class.php';
    require $root.'/includes/game/loot/loot.class.php';
    require $root.'/endpoints/item/item.php';
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException($message, 0, $level); });
    $checks = 0;
    function check(bool $ok, string $label) : void {
        global $checks;
        ++$checks;
        if (!$ok) throw new RuntimeException($label);
    }

    $payload = '$(globalThis.itemTabMarker=1)</script><script>globalThis.itemTabMarker=2</script><!--';
    LootByContainer::$rows = [['id' => 22449, 'name' => $payload, 'percent' => 100, 'count' => 1]];
    $page = (new ReflectionClass(ItemBaseResponse::class))->newInstanceWithoutConstructor();
    $helper = new ReflectionMethod($page, 'tabContains');
    $tabs = new Tabs(['parent' => 'tabs-generic']);
    $vectors = [
        [Loot::ITEM, 29254, 'contains', 'tab_contains', []],
        [Loot::PROSPECTING, 29254, 'prospecting', 'tab_prospecting', ['side', 'slot', 'reqlevel']],
        [Loot::MILLING, 29254, 'milling', 'tab_milling', ['side', 'slot', 'reqlevel']],
        [Loot::DISENCHANT, 99, 'disenchanting', 'tab_disenchanting', ['side', 'slot', 'reqlevel']]
    ];
    $expected = [];
    foreach ($vectors as [$template, $id, $tabId, $key, $hidden]) {
        $name = new JsExpression('LANG.'.$key);
        $tab = $helper->invoke($page, $template, $id, $name, $tabId, [new JsExpression('Listview.extraCols.percent')], $hidden);
        check($tab instanceof Listview, $tabId.': populated loot produces a listview');
        $options = $tab->jsonSerialize();
        check($options['name'] === $name, $tabId.': locale expression survives the helper without string coercion');
        check($tab->getId() === $tabId, $tabId.': existing tab id preserved');
        check($options['data'] === LootByContainer::$rows, $tabId.': loot rows preserved');
        check(array_map(fn($column) => $column->expression, $options['extraCols']) === ['Listview.extraCols.percent', 'Listview.extraCols.count'], $tabId.': extra columns merged and deduplicated');
        check(($options['hiddenCols'] ?? []) === $hidden, $tabId.': hidden columns preserved');
        check($options['computeDataFunc']->expression === 'Listview.funcBox.initLootTable', $tabId.': loot-table initializer remains executable');
        check(end(LootByContainer::$calls) === [$template, $id], $tabId.': correct loot template and id forwarded');
        check(end($page->globals) === [3 => [22449 => ['name' => 'Synthetic enchanting material']]], $tabId.': global data extended');
        $tabs->addListviewTab($tab);
        $expected[] = ['id' => $tabId, 'key' => $key, 'hidden' => $hidden];
    }
    // Literal custom labels must stay data, even if they resemble an expression.
    foreach (['LANG.tab_disenchanting', $payload] as $idx => $label) {
        $tab = $helper->invoke($page, Loot::ITEM, 29254, $label, 'literal-'.$idx, [], []);
        check($tab->jsonSerialize()['name'] === $label, 'Literal tab label is preserved');
        $tabs->addListviewTab($tab);
        $expected[] = ['id' => 'literal-'.$idx, 'label' => $label, 'hidden' => []];
    }
    $script = (string)$tabs;
    foreach ($vectors as [, , $tabId, $key])
        check(str_contains($script, '"name":LANG.'.$key), $tabId.': serialized tab uses a locale lookup');
    check(!preg_match('~</script|<!--~i', $script), 'Literal labels and row names cannot escape an inline script');
    $globalCount = count($page->globals);
    LootByContainer::$available = false;
    check($helper->invoke($page, Loot::DISENCHANT, 0, new JsExpression('LANG.tab_disenchanting'), 'disenchanting', []) === null, 'Empty loot does not produce a tab');
    check(count($page->globals) === $globalCount, 'Empty loot does not extend globals');

    if (in_array('--fixtures', $argv, true))
        echo json_encode(['script' => $script, 'expected' => $expected, 'rows' => LootByContainer::$rows, 'payload' => $payload], JSON_THROW_ON_ERROR);
    else
        echo "PASS: $checks item loot-tab helper/serialization checks\n";
}
