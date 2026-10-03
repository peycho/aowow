import assert from 'node:assert/strict';
import {readFileSync, readdirSync} from 'node:fs';
import vm from 'node:vm';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const assets = JSON.parse(input);
for (const [file, source] of Object.entries(assets)) new vm.Script(source, {filename: file});
if (process.argv.includes('--browser')) {
    // Legacy sources contain HTML script strings; encode fixtures without changing their JavaScript.
    const script = source => `<script>(0,eval)(new TextDecoder().decode(Uint8Array.from(atob('${Buffer.from(source).toString('base64')}'), c => c.charCodeAt(0))));</script>`;
    const locales = Object.fromEntries(readdirSync('static/js').filter(file => /^locale_.*\.js$/.test(file))
        .map(file => [file.slice(7, 11), readFileSync(`static/js/${file}`, 'utf8')]));
    const fixture = '<!doctype html><meta charset="UTF-8"><title>Retirement regression</title>\n' +
        '<link rel="stylesheet" href="static/css/Cropper.css">\n' +
        '<pre id="result">RUNNING</pre><div id="list"></div><div id="models"></div><div id="comparison"></div>' +
        '<div id="profile"></div><div id="talent"></div><div id="petcalc"></div>' +
        '<div id="crop"></div>' +
        '<a id="tooltip-link" href="?item=1" data-wowhead="item=1">Item #1</a><iframe id="widget"></iframe>\n' +
        script("var g_host=location.origin, g_staticUrl=location.pathname.startsWith('/subdir/') ? '/subdir/static' : '/static', g_serverTime=new Date(), g_dataKey='fixture';var errors=[];window.addEventListener('error',e=>errors.push(e.message));window.addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));window.addEventListener('securitypolicyviolation',e=>errors.push('Blocked resource: '+e.blockedURI));") +
        ['jquery-3.7.0.min.js', 'basic.js', 'locale_enus.js'].map(file => script(readFileSync(`static/js/${file}`, 'utf8'))).join('\n') +
        script(assets['static/js/global.js']) +
        script("var g_user={id:0,name:'',roles:0,cookies:{},completion:{},characters:[]};var g_externalLinks={forum:null};var g_gems={},g_enchants={},g_statistics={},g_pet_talents=[],g_pet_icons={},g_weightPresets=[],g_battlegroups={},g_realms={},g_glyphs=[],g_glyph_items=[],g_glyph_order=[],g_skill_order=[],g_faction_order=[],g_quest_catorder=[],g_quest_catorder_total=[],g_achievement_catorder=[],g_achievement_points={},g_excludes={};var aowow_tooltips={renamelinks:true,iconizelinks:true,colorlinks:true};") +
        ['Draggable.js', 'Cropper.js', 'filters.js', 'profile.js', 'Summary.js', 'TalentCalc.js', 'Profiler.js'].map(file => script(readFileSync(`static/js/${file}`, 'utf8'))).join('\n') +
        script(assets['static/widgets/power.js']) +
        script('var wt_presets=[];var retirementLocales='+JSON.stringify(locales).replaceAll('<', '\\u003c')+';') +
        script(readFileSync('tests/retirement-markup.js', 'utf8')) +
        script(readFileSync('tests/retirement-browser.js', 'utf8')) +
        script("setTimeout(function(){var result=document.getElementById('result');if(result.textContent==='RUNNING')result.textContent='FAIL: fixture did not finish; '+errors.join('; ');},9000);");
    await new Promise(resolve => process.stdout.write(fixture, resolve));
    process.exit(0);
}
let checks = 0;
for (const file of readdirSync('static/js').filter(file => /^locale_.*\.js$/.test(file))) {
    const context = vm.createContext({g_staticUrl: '/static', document: {}, location: {hostname: 'local.example', href: 'https://local.example/'},
        setTimeout() {}, Locale: {getName: () => file.slice(7, 11)},
        $: Object.assign(() => ({ready() {}}), {noop() {}}),
        $WH: {isset: () => false, trim: text => text.trim()},
        g_isExternalUrl: url => /^https?:/.test(url),
        g_items: {}, g_screenshots: {}, g_customColors: {}, check: (ok, message) => {++checks; assert.ok(ok, `${file}: ${message}`);}});
    vm.runInContext("String.prototype.ltrim = function() { return this.trimStart(); }; String.prototype.rtrim = function() { return this.trimEnd(); };", context);
    vm.runInContext(readFileSync(`static/js/${file}`, 'utf8'), context);
    vm.runInContext(readFileSync('setup/tools/filegen/templates/global.js/markup.js', 'utf8'), context);
    vm.runInContext(readFileSync('tests/retirement-markup.js', 'utf8'), context);
    vm.runInContext('checkRetirementMarkup(check)', context);
}
console.log(`PASS: ${checks} retirement markup checks across six locales; generated assets parse`);
