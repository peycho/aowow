// Real server schema and legacy breadcrumb hook: enhancement cannot add schema
// to private pages or create a conflicting second document on public pages.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import http from 'node:http';
import { execFileSync } from 'node:child_process';
import { browser } from './browser-cdp.mjs';
const fixtures = JSON.parse(execFileSync('php', ['tests/seo-structured-data.php', '--fixtures'], { encoding: 'utf8' }));
const script = file => '<script>{const s=document.createElement("script");s.textContent=' + JSON.stringify(fs.readFileSync(file, 'utf8')).replace(/</g, '\\u003c') + ';document.head.appendChild(s);}</script>';
const sources = ['static/js/jquery-3.7.0.min.js', 'static/js/basic.js', 'setup/tools/filegen/templates/global.js/0_user.js',
    ...['utilities', 'dom_manipulation', 'url', 'seo'].map(name => 'setup/tools/filegen/templates/global.js/' + name + '.js')].map(script).join('');
const requested = [];
const server = http.createServer((req, res) => {
    requested.push(req.url);
    const url = new URL(req.url, 'http://fixture');
    if (url.searchParams.has('data')) { res.setHeader('Content-Type', 'application/javascript'); res.end(''); return; }
    const fixture = fixtures[Number(url.searchParams.get('fixture')) || 0];
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    if (url.searchParams.has('blocked')) res.setHeader('Content-Security-Policy', "script-src 'none'");
    res.end('<!doctype html><meta charset="utf-8">' + fixture.html + sources +
        '<script>window.g_pageInfo={name:"Visible title"};$WH.Seo.addJsonLdBreadcrumb([{name:"Items",url:location.origin+"/?items"}]);</script>');
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const page = await browser();
let checks = 0;
try {
    for (const blocked of [false, true]) {
        for (let index = 0; index < fixtures.length; ++index) {
            requested.length = 0;
            await page.goto('http://127.0.0.1:' + server.address().port + '/?fixture=' + index + (blocked ? '&blocked' : ''));
            const expected = fixtures[index].types;
            const state = await page.evaluate(`(()=>{
                const scripts=[...document.querySelectorAll('script[type="application/ld+json"]')];
                return {count:scripts.length, types:scripts.length ? JSON.parse(scripts[0].textContent)['@graph'].map(n=>n['@type']) : [],
                    legacy:!!document.querySelector('#json-ld-breadcrumbs')};
            })()`);
            assert.equal(state.count, expected.length ? 1 : 0, fixtures[index].query); ++checks;
            assert.deepEqual(state.types, expected); ++checks;
            assert.equal(state.legacy, false); ++checks;
            assert.deepEqual(page.errors, [], fixtures[index].query + ' requests: ' + JSON.stringify(requested)); ++checks;
        }
    }
    console.log(`PASS: ${checks} initial/enhanced/private structured-data browser checks`);
} finally { await page.close(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
