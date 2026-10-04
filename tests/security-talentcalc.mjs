// Feed actual generated datasets from `php tests/security-talentcalc.php --fixtures`.
import assert from 'node:assert/strict';
import { runInNewContext } from 'node:vm';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const { files, payload } = JSON.parse(input);
let checks = 0;
function equal(actual, expected, label) { ++checks; assert.deepEqual(actual, expected, label); }
for (const [file, source] of Object.entries(files)) {
    let registered;
    const inventory = [];
    inventory[16] = [123];
    const context = {
        aowowSecurityMarker: 0,
        $WowheadTalentCalculator: { registerClass: (id, tree) => { registered = { id, tree }; } },
        _inventory: { getInventory: () => inventory },
        g_items: { 123: { jsonequip: { classs: 2, subclass: 0 } } }
    };
    runInNewContext(source, context, { timeout: 1000 });
    const isPet = file.endsWith('/pet-talents');
    const tree = isPet ? context.g_pet_talents : registered.tree;
    equal(context.aowowSecurityMarker, 0, `${file}: text must not execute`);
    equal(tree[0].n, payload, `${file}: tree name remains data`);
    const talent = tree[0].t[0];
    equal(talent.n, payload, `${file}: talent name remains data`);
    equal(talent.d[0], payload, `${file}: tooltip remains data`);
    equal(talent.t[0], payload, `${file}: tooltip header remains data`);
    equal(talent.s[0], 101, `${file}: spell rank preserved`);
    if (isPet) {
        equal(context.g_pet_icons[1], 'ability_fixture', 'Pet icons preserved');
        equal(talent.f[0], 1, 'Pet family preserved');
        equal(talent.j.length, 0, 'Pet talents have no class modifiers');
    } else {
        equal(registered.id, Number(file.split('-').at(-1)), 'Class registration id preserved');
        const modifier = talent.j[0].mlecritstrkpct;
        equal(modifier[1], 'functionOf', 'Profiler modifier type preserved');
        equal(typeof modifier[2], 'function', 'Profiler callback is executable');
        equal(modifier[2](), 5, 'Matching equipped weapon grants the modifier');
        context.g_items[123].jsonequip.subclass = 1;
        equal(modifier[2](), 0, 'Other weapons do not grant the modifier');
        inventory[16] = [0];
        equal(modifier[2](), 0, 'Empty weapon slot does not grant the modifier');
    }
}
process.stdout.write(`PASS: ${checks} generated talent JavaScript and security checks\n`);
