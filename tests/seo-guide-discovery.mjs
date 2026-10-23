import assert from 'node:assert/strict';
import fs from 'node:fs';
import http from 'node:http';
import { execFileSync } from 'node:child_process';
import { browser } from './browser-cdp.mjs';
const fixtures = JSON.parse(execFileSync('php', ['tests/seo-guide-discovery.php', '--fixtures'], { encoding: 'utf8' }));
const css = ['basic', 'global', 'aowow', 'responsive'].map(name => fs.readFileSync('static/css/' + name + '.css', 'utf8')).join('\n');
const server = http.createServer((req, res) => {
    const fixture = fixtures[Number(new URL(req.url, 'http://fixture').searchParams.get('locale')) || 0];
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.end('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' + css + '</style><div class="layout"><div class="main"><div class="main-contents"><div class="text">' + fixture.html + '</div></div></div></div>');
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const page = await browser();
let checks = 0;
try {
    for (const width of [360, 390, 768, 1440]) {
        await page.viewport(width);
        for (let index = 0; index < fixtures.length; ++index) {
            await page.goto('http://127.0.0.1:' + server.address().port + '/?locale=' + index);
            const state = await page.evaluate(`(()=>{
                const links=[...document.querySelectorAll('#guide-categories a,#related-guides a')];
                return {overflow:document.documentElement.scrollWidth>innerWidth+1,count:links.length,
                    visible:links.every(a=>{const r=a.getBoundingClientRect();return r.width>0&&r.height>0&&r.left>=-1&&r.right<=innerWidth+1}),
                    introduction:document.querySelector('#guide-introduction').textContent,
                    injected:!!document.querySelector('#related-guides script')};
            })()`);
            assert.equal(state.overflow, false, 'page reflow at ' + width + ' locale ' + fixtures[index].locale); ++checks;
            assert.equal(state.count, 5); ++checks;
            assert.equal(state.visible, true, 'category and long related links remain readable'); ++checks;
            assert.ok(state.introduction.length > 0); ++checks;
            assert.equal(state.injected, false); ++checks;
            assert.deepEqual(page.errors, []); ++checks;
        }
    }
    console.log(`PASS: ${checks} localized guide navigation and related-link layout browser checks`);
} finally { await page.close(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
