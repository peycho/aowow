// Execute item loot tabs against the actual translated LANG tables in every supported JS locale.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const fixture = JSON.parse(input);
let checks = 0;
function equal(actual, expected, label) { ++checks; assert.deepEqual(actual, expected, label); }
for (const locale of ['enus', 'dede', 'frfr', 'eses', 'ruru', 'zhcn']) {
    const source = readFileSync(new URL(`../static/js/locale_${locale}.js`, import.meta.url), 'utf8');
    const names = source.match(/var LANG = \{[\s\S]*?\n\};/);
    assert.ok(names, `${locale}: actual locale table exists`); ++checks;
    const listviews = [];
    function Listview(options) { listviews.push(options); }
    Listview.extraCols = { percent: { id: 'percent' }, count: { id: 'count' } };
    Listview.funcBox = { initLootTable() {} };
    function Tabs(options) { this.options = options; }
    const context = { Listview, Tabs, itemTabMarker: 0 };
    runInNewContext(names[0], context, { timeout: 1000 });
    runInNewContext(fixture.script, context, { timeout: 1000 });
    equal(context.itemTabMarker, 0, `${locale}: labels and rows never execute`);
    equal(listviews.length, fixture.expected.length, `${locale}: all populated loot tabs generated`);
    for (const [idx, expected] of fixture.expected.entries()) {
        const tab = listviews[idx];
        equal(tab.id, expected.id, `${locale}: tab id preserved`);
        if (expected.key) {
            assert.ok(typeof context.LANG[expected.key] === 'string' && context.LANG[expected.key].length > 0); ++checks;
            equal(tab.name, context.LANG[expected.key], `${locale}: localized ${expected.id} label`);
            assert.notEqual(tab.name, 'LANG.' + expected.key); ++checks;
        }
        else equal(tab.name, expected.label, `${locale}: custom label remains literal data`);
        equal(JSON.parse(JSON.stringify(tab.data)), fixture.rows, `${locale}: loot data preserved and safely escaped`);
        equal(tab.computeDataFunc, Listview.funcBox.initLootTable, `${locale}: callable loot-table initializer`);
        equal(tab.extraCols[0], Listview.extraCols.percent, `${locale}: percent column stays a reference`);
        equal(tab.extraCols[1], Listview.extraCols.count, `${locale}: loot extra columns preserved`);
        equal(JSON.parse(JSON.stringify(tab.hiddenCols || [])), expected.hidden, `${locale}: hidden columns preserved`);
        equal(tab.tabs, context.myTabs, `${locale}: loot listview attached to Related tabs`);
    }
}
process.stdout.write(`PASS: ${checks} localized item loot-tab JavaScript checks\n`);
