// Real image markup and native IntersectionObserver/network behavior. Mapper's
// constructor is spied on; populated-site acceptance covers its actual renderer.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import http from 'node:http';
import { execFileSync } from 'node:child_process';
import { browser } from './browser-cdp.mjs';

const script = file => '<script>{const s=document.createElement("script");s.textContent=' + JSON.stringify(fs.readFileSync(file, 'utf8')).replace(/</g, '\\u003c') + ';document.head.appendChild(s);}</script>';
const scripts = ['static/js/jquery-3.7.0.min.js', 'static/js/basic.js', 'setup/tools/filegen/templates/global.js/0_user.js', 'static/js/locale_enus.js',
    ...['utilities', 'dom_manipulation', 'markup', 'listview', 'listview_templates'].map(name => 'setup/tools/filegen/templates/global.js/' + name + '.js')].map(script).join('');
const requested = [];
let images;
const server = http.createServer((req, res) => {
    requested.push(req.url);
    const url = new URL(req.url, 'http://fixture');
    if (url.searchParams.has('data')) { res.setHeader('Content-Type', 'application/javascript'); res.end(''); return; }
    if (req.url.startsWith('/static/images/logos/')) {
        const name = req.url.split('/').at(-1);
        if (!['home.png', 'header.png'].includes(name)) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type', 'image/png'); res.end(fs.readFileSync('static/images/logos/' + name)); return;
    }
    if (req.url.startsWith('/map-image') || req.url.startsWith('/static/uploads/')) { res.writeHead(204); res.end(); return; }
    res.setHeader('Content-Type', 'text/html');
    if (url.searchParams.has('noscript')) res.setHeader('Content-Security-Policy', "script-src 'none'");
    res.end(`<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>body{margin:0}.text img{max-width:100%;height:auto}.mapper{border:3px solid #404040;margin-top:10px}.shell{max-width:100%;overflow:auto}.spacer{height:4000px}</style>
<script>window.g_staticUrl=location.origin+'/static';window.g_host=location.origin;window.g_user={roles:0};window.g_serverTime=new Date();window.Locale={getName:()=> 'enus'};window.ContactTool={show(){}};window.g_formatDate=function(){};window.g_addTooltip=function(){};if(location.search.includes('noio'))window.IntersectionObserver=undefined;</script>
${scripts}
<script>window.madeMaps=[];window.Mapper=function(opt){madeMaps.push({id:opt.parent.id,unique:opt.unique,zone:opt.zone,coords:opt.coords});opt.parent.insertAdjacentHTML('beforeend','<img src="/map-image?id='+opt.unique+'"><button>Toggle pins</button><span class="pin">Pin</span>');opt.parent.querySelector('button').onclick=function(){opt.parent.querySelector('.pin').hidden=!opt.parent.querySelector('.pin').hidden;}};Mapper.sizes=[[488,325]];</script>
<div class="text" id="near-image">${images.near}</div><div class="shell" id="near-map"></div>
<div class="spacer"></div><div class="text" id="far-image">${images.far}</div>
<div class="spacer"></div><div class="shell" id="far-map"></div><div class="spacer"></div><div class="shell" id="last-map"></div>
<div id="scaled">${images.scaled}</div><div id="authored">${images.authored}</div><div id="invalid">${images.invalid}</div><table><tr><td id="screenshot"></td></tr></table>
<script>
Markup.printHtml('[img src="'+g_staticUrl+'/images/logos/home.png"]','near-image');
Markup.printHtml('[img src="'+g_staticUrl+'/images/logos/header.png"]','far-image');
for(const id of ['near-map','far-map','last-map'])Markup.printHtml('[map zone=7][pin x=25 y=50]A pin[/pin][/map]',id);
Listview.templates.screenshot.compute.call({}, {id:123,date:'2026-10-22',width:1600,height:900},document.getElementById('screenshot'),0);
</script>`);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = 'http://127.0.0.1:' + server.address().port;
images = JSON.parse(execFileSync('php', ['tests/fixtures/performance-content.php', origin], { encoding: 'utf8' }));
let page, checks = 0;
function check(ok, message) { ++checks; assert.ok(ok, message); }
try {
    page = await browser();
    for (const width of [390, 1440]) {
        requested.length = 0; await page.viewport(width); await page.call('Network.clearBrowserCache'); await page.goto(origin);
        check(page.errors.length === 0, 'real image/map markup runs: ' + page.errors.join(' | '));
        check(await page.evaluate(`(()=>{const i=document.querySelector('#near-image img');return i.loading==='lazy'&&i.decoding==='async'&&i.width===261&&i.height===119&&i.complete;})()`), 'visible image loads and keeps server-inferred dimensions through enhancement');
        check(await page.evaluate(`(()=>{const i=document.querySelector('#far-image img');return i.loading==='lazy'&&i.hasAttribute('width')&&i.hasAttribute('height');})()`), 'below-fold image keeps loading and geometry attributes');
        check(!requested.includes('/static/images/logos/header.png'), 'below-fold image is not fetched during initial rendering');
        const visibleMaps = await page.evaluate('madeMaps');
        check(visibleMaps.length === 1 && String(visibleMaps[0].zone) === '7' && JSON.stringify(visibleMaps[0].coords).includes('25,50'), 'visible map initializes immediately and keeps its pin coordinates: ' + JSON.stringify(visibleMaps));
        check(await page.evaluate(`(()=>{const e=document.querySelector('#far-map .mapper'),r=e.getBoundingClientRect();return r.width===494&&r.height===331&&getComputedStyle(e).cssFloat==='left'&&e.getAttribute('aria-busy')==='true';})()`), 'deferred map reserves native geometry and float before initialization');
        check(await page.evaluate(`new Set([...document.querySelectorAll('.mapper')].map(e=>e.id)).size===3`), 'maps from separate markup renders have distinct IDs');
        check(!requested.some(url=>url.startsWith('/map-image?id=1')), 'deferred map does not fetch its image initially');
        check(await page.evaluate(`(()=>{const i=document.querySelector('#scaled img');return i.width===130&&i.height===59;})()`), 'one authored dimension derives the other from the local image ratio');
        check(await page.evaluate(`(()=>{const i=document.querySelector('#authored img');return i.width===130&&i.height===70;})()`), 'explicit authored dimensions retain priority');
        check(await page.evaluate(`!document.querySelector('#invalid img')`), 'dimension inference keeps disallowed image paths rejected');
        check(await page.evaluate(`(()=>{const i=document.querySelector('#screenshot img');return i.loading==='lazy'&&i.decoding==='async'&&i.width===150&&i.height===84;})()`), 'screenshot thumbnails reserve their displayed aspect ratio');
        await page.evaluate(`document.querySelector('#far-image').scrollIntoView()`); await page.sleep(350);
        check(requested.includes('/static/images/logos/header.png'), 'scrolling near the deferred article image fetches it');
        await page.evaluate(`document.querySelector('#far-map').scrollIntoView()`); await page.sleep(350);
        check(await page.evaluate(`madeMaps.length===2&&!document.querySelector('#far-map [aria-busy]')`), 'native observer initializes the map on approach');
        check(await page.evaluate(`(()=>{const r=document.querySelector('#far-map .mapper').getBoundingClientRect();return r.width===494&&r.height===331;})()`), 'map initialization preserves reserved geometry');
        await page.evaluate(`document.querySelector('#far-map button').click()`);
        check(await page.evaluate(`document.querySelector('#far-map .pin').hidden`), 'deferred map controls remain interactive');
        await page.evaluate(`window.dispatchEvent(new Event('beforeprint'))`);
        check(await page.evaluate(`madeMaps.length===3&&Markup.pendingMaps.size===0`), 'printing initializes all remaining maps once');
        await page.evaluate(`Markup.printHtml('[img src="'+g_staticUrl+'/images/logos/home.png" width=80]','near-image')`);
        check(await page.evaluate(`document.querySelector('#near-image img').getAttribute('width')==='80'&&!document.querySelector('#near-image img').hasAttribute('height')`), 'later editor changes do not inherit old server geometry');
    }
    await page.goto(origin + '/?noio');
    check(await page.evaluate('madeMaps.length===3'), 'browsers without IntersectionObserver retain eager functional maps');
    await page.goto(origin);
    await page.evaluate(`document.querySelector('#last-map').remove();Markup.createMaps()`); await page.sleep(200);
    check(await page.evaluate('madeMaps.length===1&&Markup.pendingMaps.size===1'), 'removed deferred maps are discarded without constructing stale widgets');
    await page.goto(origin + '/?noscript');
    check(await page.evaluate(`(()=>{const i=document.querySelector('#near-image img');return i.getAttribute('data-server-image')!==null&&i.width===261&&i.height===119;})()`), 'script-blocked initial images retain inferred geometry');
    console.log(`PASS: ${checks} image loading, geometry, deferred map and fallback checks`);
} finally { await page?.close(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
