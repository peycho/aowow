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

for (const file of ['locale_enus.js', 'locale_frfr.js', 'locale_dede.js', 'locale_zhcn.js', 'locale_eses.js', 'locale_ruru.js']) {
    for (const enabled of [false, true]) {
        const context = vm.createContext({g_staticUrl: '/static'});
        vm.runInContext(readFileSync(`static/js/${file}`, 'utf8'), context);
        vm.runInContext(readFileSync('static/js/external-links.js', 'utf8'), context);
        const tools = context.mn_tools, more = context.mn_more;
        const originalTools = JSON.stringify(tools), originalMore = JSON.stringify(more);
        context.g_applyProfilerMenus(tools, more, enabled);
        assert.equal(context.mn_path.find(entry => entry[0] === 1)[3], tools);
        assert.equal(context.mn_path.find(entry => entry[0] === 2)[3], more);
        if (enabled) {
            assert.equal(JSON.stringify(tools), originalTools);
            assert.equal(JSON.stringify(more), originalMore);
        } else {
            assert.equal(tools.find(entry => entry[0] === 5), undefined);
            const help = more.find(entry => entry[0] === 13)[3];
            assert.equal(help.find(entry => entry[0] === 6), undefined);
            assert.ok(help.some(entry => entry[2] === '?help=stat-weighting'));
            for (const id of [0, 2, 3, 1, 8]) assert.ok(tools.some(entry => entry[0] === id));
            assert.ok(!JSON.stringify([tools, more]).match(/\?profiler|\?profiles|\?guilds|\?arena-teams|\?profile&new|\?help=profiler/));
        }
        const once = JSON.stringify([tools, more]);
        context.g_applyProfilerMenus(tools, more, enabled);
        assert.equal(JSON.stringify([tools, more]), once);
    }
}
console.log('PASS: 12 profiler navigation scenarios across all locales');

for (const file of ['locale_enus.js', 'locale_frfr.js', 'locale_dede.js', 'locale_zhcn.js', 'locale_eses.js', 'locale_ruru.js']) {
    for (const searchplugins of [false, true]) for (const searchbox of [false, true]) {
        const context = vm.createContext({g_staticUrl: '/static'});
        vm.runInContext(readFileSync(`static/js/${file}`, 'utf8'), context);
        vm.runInContext(readFileSync('static/js/external-links.js', 'utf8'), context);
        const more = context.mn_more;
        const original = more.map(entry => [...entry]);
        const tools = JSON.stringify(context.mn_tools);
        context.g_applyGoodiesMenus(more, searchplugins, searchbox);
        assert.equal(context.mn_path.find(entry => entry[0] === 2)[3], more, `${file}: shared menu reference remains intact`);
        for (const entry of original.filter(entry => entry[0] != null)) {
            const current = more.find(item => item[0] === entry[0]);
            if ((entry[0] === 8 && !searchplugins) || (entry[0] === 16 && !searchbox))
                assert.equal(current, undefined, `${file}: disabled goodies entry is absent`);
            else
                assert.equal(JSON.stringify(current), JSON.stringify(entry), `${file}: enabled and unrelated entries retain their labels and URLs`);
        }
        assert.equal(JSON.stringify(context.mn_tools), tools, `${file}: ordinary search and other tools remain unchanged`);
        const once = JSON.stringify(more);
        context.g_applyGoodiesMenus(more, searchplugins, searchbox);
        assert.equal(JSON.stringify(more), once, `${file}: repeated application is stable`);
        context.g_applyProfilerMenus(context.mn_tools, more, false);
        assert.ok(more.some(entry => entry[0] === 10), `${file}: tooltips remain available alongside the profiler switch`);
    }
}
console.log('PASS: 24 independent goodies navigation scenarios across all locales');

for (const file of ['locale_enus.js', 'locale_frfr.js', 'locale_dede.js', 'locale_zhcn.js', 'locale_eses.js', 'locale_ruru.js']) {
    for (const enabled of [false, true]) for (const profilerEnabled of [false, true]) {
        const context = vm.createContext({g_staticUrl: '/static'});
        vm.runInContext(readFileSync(`static/js/${file}`, 'utf8'), context);
        vm.runInContext(readFileSync('static/js/external-links.js', 'utf8'), context);
        const tools = context.mn_tools;
        context.g_applyProfilerMenus(tools, context.mn_more, profilerEnabled);
        const utilities = tools.find(entry => entry[0] === 8)[3];
        const original = JSON.stringify(tools), originalUtilities = utilities.map(entry => [...entry]);
        const more = JSON.stringify(context.mn_more);
        context.g_applyMissingScreenshotsMenu(tools, enabled);
        assert.equal(context.mn_path.find(entry => entry[0] === 1)[3], tools, `${file}: shared tools reference remains intact`);
        assert.equal(tools.find(entry => entry[0] === 8)[3], utilities, `${file}: shared utilities reference remains intact`);
        if (enabled) assert.equal(JSON.stringify(tools), original, `${file}: enabled utility retains its localized navigation`);
        else {
            assert.equal(utilities.find(entry => entry[0] === 13), undefined, `${file}: disabled missing screenshots is hidden`);
            for (const entry of originalUtilities.filter(entry => entry[0] != null && entry[0] !== 13))
                assert.equal(JSON.stringify(utilities.find(item => item[0] === entry[0])), JSON.stringify(entry), `${file}: other utilities retain their labels and URLs`);
            assert.ok(!JSON.stringify(tools).includes('?missing-screenshots'));
        }
        assert.equal(JSON.stringify(context.mn_more), more, `${file}: goodies and help remain unchanged`);
        const once = JSON.stringify(tools);
        context.g_applyMissingScreenshotsMenu(tools, enabled);
        assert.equal(JSON.stringify(tools), once, `${file}: repeated application is stable`);
    }
}
console.log('PASS: 24 missing-screenshots/profiler navigation scenarios across all locales');
