// Real Chromium navigation checks, including a previous document finishing late.
import assert from 'node:assert/strict';
import http from 'node:http';
import { browser } from './browser-cdp.mjs';

let oldImage, oldRequested = false, checks = 0;
const pixel = Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64');
const server = http.createServer(async (req, res) => {
    if (req.url === '/old-image') { oldImage = res; oldRequested = true; return; }
    if (req.url === '/old') {
        res.setHeader('Content-Type', 'text/html');
        res.end('<!doctype html><img src="/old-image">'); return;
    }
    if (req.url === '/delayed') {
        oldImage.setHeader('Content-Type', 'image/gif'); oldImage.end(pixel);
        await new Promise(resolve => setTimeout(resolve, 300));
        res.setHeader('Content-Type', 'text/html');
        res.end('<!doctype html><script src="/ready.js"></script><p id="ready">Delayed document</p>'); return;
    }
    if (req.url === '/ready.js') {
        await new Promise(resolve => setTimeout(resolve, 400));
        res.setHeader('Content-Type', 'application/javascript'); res.end('window.navigationReady=true;'); return;
    }
    if (req.url === '/late-menu') {
        res.setHeader('Content-Type', 'text/html');
        res.end('<!doctype html><script>addEventListener("load",()=>setTimeout(()=>{const a=document.createElement("a");a.id="late-menu";a.textContent="Browse";document.body.append(a);},500));</script>'); return;
    }
    if (req.url === '/redirect') { res.writeHead(302, { Location: '/ready' }); res.end(); return; }
    if (req.url === '/download') {
        res.writeHead(200, { 'Content-Type': 'application/octet-stream', 'Content-Disposition': 'attachment; filename="fixture.bin"' }); res.end('fixture'); return;
    }
    if (req.url === '/abort') { req.socket.destroy(); return; }
    res.setHeader('Content-Type', 'text/html');
    res.end('<!doctype html><p id="ready">Ready document</p>');
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = 'http://127.0.0.1:' + server.address().port;
let page;
function check(ok, message) { ++checks; assert.ok(ok, message); }
try {
    page = await browser();
    check(await page.evaluate('location.href') === 'about:blank', 'test starts in an owned blank page');
    await page.call('Page.navigate', { url: origin + '/old' });
    for (let i = 0; i < 100 && !oldRequested; ++i) await page.sleep(20);
    check(oldRequested, 'previous document has an outstanding resource');
    await page.goto(origin + '/delayed');
    check(await page.evaluate('window.navigationReady===true && !!document.querySelector("#ready")'), 'previous document load cannot finish navigation to a still-loading document');
    await page.goto(origin + '/late-menu');
    await page.waitFor(`!!document.querySelector('#late-menu')`, { description: 'the delayed menu button' });
    check(await page.evaluate(`document.querySelector('#late-menu').textContent==='Browse'`), 'readiness waits for DOM initialization after the load event');
    await assert.rejects(page.waitFor('false', { timeout: 50, description: 'missing fixture element' }), /Timed out waiting for: missing fixture element/);
    ++checks;
    await assert.rejects(page.waitFor('throw new Error("fixture script failure")'), /fixture script failure/);
    ++checks;
    await page.goto(origin + '/redirect');
    check(await page.evaluate('location.pathname==="/ready" && !!document.querySelector("#ready")'), 'HTTP redirect waits for its destination');
    await page.goto(origin + '/ready#fragment');
    check(await page.evaluate('location.hash==="#fragment"'), 'same-document navigation completes without a new load event');
    for (const width of [360, 1440, 390]) {
        await page.viewport(width); await page.goto(origin + '/ready?width=' + width);
        check(await page.evaluate('!!document.querySelector("#ready")'), 'navigation completes after viewport change to ' + width);
    }
    await assert.rejects(page.goto(origin + '/download'), error => error.message.includes('net::ERR_ABORTED') && error.message.includes('/download'));
    ++checks;
    await assert.rejects(page.goto(origin + '/abort'), error => error.message.includes('net::ERR_EMPTY_RESPONSE') && error.message.includes('/abort'));
    ++checks;
    await page.goto(origin + '/ready');
    check(await page.evaluate('!!document.querySelector("#ready")'), 'navigation works after an explicitly reported failure');
    check(page.errors.length === 0, 'navigation fixtures have no script exceptions');
    console.log(`PASS: ${checks} browser navigation, delayed load, redirect and failure checks`);
} finally {
    await page?.close(); oldImage?.end();
    server.closeAllConnections(); await new Promise(resolve => server.close(resolve));
}
