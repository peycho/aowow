// Exercise the actual Mapper floor menu using datasets from setup-maps.php.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const { files, images } = JSON.parse(input);
const mapper = readFileSync(new URL('../setup/tools/filegen/templates/global.js/mapper.js', import.meta.url), 'utf8');
let checks = 0;
function check(value, label) { ++checks; assert.ok(value, label); }
for (const [file, dataset] of Object.entries(files)) {
    let menu;
    const context = { Menu: { showAtCursor: items => { menu = items; } } };
    runInNewContext(mapper, context, { timeout: 1000 });
    runInNewContext(dataset, context, { timeout: 1000 });
    const locale = file.split('/')[1];
    for (const [zone, floors] of Object.entries(context.Mapper.multiLevelZones)) {
        let selected;
        const instance = { zone: Number(zone), level: 0, setMap: (image, level, force) => { selected = { image, level, force }; } };
        context.Mapper.prototype.showFloors.call(instance, {});
        check(menu.length === floors.length, `${locale}/${zone}: one menu item per generated floor`);
        for (let i = 0; i < floors.length; ++i) {
            check(images.includes(`static/images/wow/maps/${locale}/original/${floors[i]}.jpg`), 'Mapper references a generated image');
            check(menu[i][1] === context.g_zone_areas[zone][i], 'Menu label follows image position');
            check(typeof menu[i][1] === 'string' && menu[i][1].length > 0, 'Every menu item has a label');
            menu[i][2]();
            assert.deepEqual(selected, { image: floors[i], level: i, force: true });
            ++checks;
        }
    }
    for (const zone of [1176, 1977, 3428, 9002, 4723])
        check(context.Mapper.multiLevelZones[zone] === undefined, `${zone}: single image keeps the default Mapper filename`);
}
process.stdout.write(`PASS: ${checks} generated map JavaScript and floor-menu checks\n`);
