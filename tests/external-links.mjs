import assert from 'node:assert/strict';
import {readFileSync, readdirSync} from 'node:fs';
import vm from 'node:vm';

let checks = 0;
const keys = {3: 'forum', 7: 'blog', 4: 'irc', 6: 'facebook', 5: 'twitter', 12: 'discord'};
for (const file of readdirSync('static/js').filter(file => /^locale_.*\.js$/.test(file))) {
    for (const mask of [63, 0, 1, 7, 8, 16, 32, 31]) {
        const context = vm.createContext({g_staticUrl: '/static'});
        vm.runInContext(readFileSync(`static/js/${file}`, 'utf8'), context);
        vm.runInContext(readFileSync('static/js/external-links.js', 'utf8'), context);
        const menu = context.mn_community;
        const original = menu.map(entry => [...entry]);
        assert.ok(original.some(entry => entry[0] === 12), `${file}: Discord is available in the localized menu`);
        const links = Object.fromEntries(Object.values(keys).map((key, i) => [key, mask & (1 << i) ? `https://example.com/${key}` : null]));
        context.g_applyExternalLinks(menu, links);
        assert.equal(context.mn_path.find(entry => entry[0] === 3)[3], menu, `${file}: shared menu reference remains intact`);
        for (const entry of original.filter(entry => entry[0] != null)) {
            const key = keys[entry[0]];
            const current = menu.find(item => item[0] === entry[0]);
            if (key && !links[key]) assert.equal(current, undefined, `${file}: disabled ${key} is absent`);
            else {
                assert.ok(current, `${file}: enabled/internal entry remains`);
                assert.equal(current[1], entry[1], `${file}: localized label is preserved`);
                assert.equal(current[2], key ? links[key] : entry[2], `${file}: configured or internal URL`);
                assert.equal(current[4], entry[4], `${file}: icon options are preserved`);
            }
        }
        for (let i = 0; i < menu.length; ++i)
            if (menu[i][0] == null)
                assert.ok(i < menu.length - 1 && menu[i + 1][0] != null, `${file}: empty section headings are removed`);
        const snapshot = JSON.stringify(menu);
        context.g_applyExternalLinks(menu, links);
        assert.equal(JSON.stringify(menu), snapshot, `${file}: repeated application is stable`);
        ++checks;
    }
}
console.log(`PASS: ${checks} external link menu scenarios across all locales`);
