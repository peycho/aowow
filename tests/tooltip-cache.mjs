// Returning-visitor regression: use Chrome's real HTTP cache and the actual local widget.
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {createServer} from 'node:http';
import {browser} from './browser-cdp.mjs';

const assets = JSON.parse(execFileSync('php', ['tests/retirement.php', '--fixtures'], {encoding:'utf8'}));
const basic = readFileSync('static/js/basic.js', 'utf8');
const power = assets['static/widgets/power.js'];
// Recreate the old event API and widget call removed by the November import.
const legacyBasic = basic + '\n$WH.$E=function(e){e._target=e.target;return e;};';
const legacyPower = power.replace('var t = e.target;', 'e = $WH.$E(e); var t = e._target;');
assert.notEqual(legacyPower, power, 'Legacy fixture contains the old event-helper call');
const markup = Object.fromEntries(['old', 'updated'].map(stage => [stage, JSON.parse(execFileSync('php', [
    'tests/asset-versions.php', '--fixtures', '--revision='+(stage === 'old' ? 78 : 79),
    ...(stage === 'updated' ? ['--updated'] : [])
], {encoding:'utf8'}))]));
let stage = 'old';
const requests = [];
const allRequests = [];
const server = createServer((req, res) => {
    allRequests.push(req.url);
    const url = new URL(req.url, 'http://fixture.test');
    const path = url.pathname;
    if (!url.search && ['/old', '/updated', '/unversioned/old', '/unversioned/updated'].includes(path)) {
        let {js, css} = markup[stage];
        if (path.startsWith('/unversioned/')) {
            js = js.replace(/v=\d+&/g, ''); css = css.replace(/v=\d+&/g, '');
        }
        res.setHeader('Content-Type', 'text/html');
        res.setHeader('Cache-Control', 'no-store');
        res.end(`<!doctype html><meta charset="utf-8">${css}
<script>var g_host=location.origin,g_staticUrl='/static';</script>
<script src="/jquery.js"></script>${js}
<div class="listview"><div class="iconmedium"><a id="icon" href="?item=1" style="display:block;width:36px;height:36px">Icon</a></div>
<a id="name" href="?item=1">Fixture Sword</a></div>`);
        return;
    }
    res.setHeader('Content-Type', 'text/javascript');
    if (url.searchParams.has('power')) {
        res.end('$WowheadPower.registerItem(1,0,{name_enus:"Fixture Sword",quality:2,tooltip_enus:"<table><tr><td>Warm cache item tooltip</td></tr></table>",map:{},spells:{}});');
        return;
    }
    if (url.searchParams.has('data')) {
        res.end('if(window.$WowheadPower)$WowheadPower.loadScales('+(url.searchParams.get('data') === 'spell-scaling' ? 6 : 3)+',0);');
        return;
    }
    res.setHeader('Cache-Control', 'public, max-age=3600');
    requests.push(req.url);
    if (path === '/static/js/basic.js') res.end(stage === 'old' ? legacyBasic : basic);
    else if (path === '/static/widgets/power.js') res.end(stage === 'old' ? legacyPower : power);
    else if (path === '/jquery.js') res.end(readFileSync('static/js/jquery-3.7.0.min.js'));
    else if (path === '/static/css/basic.css') {
        res.setHeader('Content-Type', 'text/css'); res.end(readFileSync('static/css/basic.css'));
    }
    else {res.writeHead(204);res.end();}
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
let page, checks = 0;
function check(ok, message) {++checks;assert.ok(ok, message);}
async function hover(id) {
    await page.call('Input.dispatchMouseEvent', {type:'mouseMoved',x:600,y:600});
    const rect = await page.evaluate(`(()=>{const r=document.getElementById('${id}').getBoundingClientRect();return{x:r.x+r.width/2,y:r.y+r.height/2}})()`);
    await page.call('Input.dispatchMouseEvent', {type:'mouseMoved', ...rect});
}
const visible = `!!$WH.Tooltip.tooltip && getComputedStyle($WH.Tooltip.tooltip).display!=='none' && getComputedStyle($WH.Tooltip.tooltip).visibility==='visible' && $WH.Tooltip.tooltip.textContent.includes('Warm cache item tooltip')`;
try {
    page = await browser();
    await page.viewport(1440, false);
    // First prove the previous timestamp-only policy fails for a returning visitor.
    stage = 'old';
    await page.goto(`http://127.0.0.1:${server.address().port}/unversioned/old`);
    await hover('name'); await page.waitFor(visible);
    check(!page.errors.length, 'Historical basic/widget pair works before deployment: '+page.errors.join(' | ')+'; requests '+allRequests.join(', '));
    stage = 'updated';
    await page.goto(`http://127.0.0.1:${server.address().port}/unversioned/updated`);
    await hover('name');
    await page.waitFor('typeof $WH.$E === "undefined"');
    check(page.errors.some(error => error.includes('$WH.$E is not a function')), 'Timestamp-only warm cache reproduces the reported hover exception');
    check(requests.filter(url => url === '/static/widgets/power.js?1700000000').length === 1, 'Broken widget was reused from the HTTP cache, not downloaded again');
    check(!(await page.evaluate(visible)), 'The mixed cached scripts fail to display a tooltip');
    // Keep this browser profile and HTTP cache; simulate two versioned releases.
    stage = 'old';
    await page.goto(`http://127.0.0.1:${server.address().port}/old`);
    await hover('name'); await page.waitFor(visible);
    check(!page.errors.length, 'Old release also works with release-versioned URLs: '+page.errors.join(' | '));
    stage = 'updated';
    await page.goto(`http://127.0.0.1:${server.address().port}/updated`);
    for (const id of ['icon', 'name']) {
        await hover(id); await page.waitFor(visible);
        check(!page.errors.length, 'Returning visitor sees '+id+' tooltip after ordinary navigation: '+page.errors.join(' | '));
        await page.call('Input.dispatchMouseEvent', {type:'mouseMoved',x:600,y:600});
        check(!(await page.evaluate(visible)), 'Tooltip hides when leaving the '+id);
    }
    check(requests.filter(url => /^\/static\/widgets\/power.js\?v=79&/.test(url)).length === 1, 'Release change downloads the current widget despite an unchanged timestamp');
    check(await page.evaluate('typeof $WH.$E === "undefined"'), 'Current widget works with the native event API without restoring the removed helper');
    console.log(`PASS: ${checks} returning-visitor tooltip checks with the real Chromium HTTP cache`);
}
finally {
    await page?.close(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve));
}
