// Exercise the shared Mapper with controlled image events and real HTTP images.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../setup/tools/filegen/templates/global.js/mapper.js', import.meta.url), 'utf8');
const locales = ['enus', 'frfr', 'dede', 'zhcn', 'eses', 'ruru'];
let checks = 0;
function equal(actual, expected, label) { ++checks; assert.deepEqual(actual, expected, label); }
const url = (locale, map) => `/static/images/wow/maps/${locale}/original/${map}.jpg`;
const background = (locale, map) => `url(${url(locale, map)})`;

function fixture(locale) {
    const images = [];
    const context = {
        g_staticUrl: '/static', Locale: { getName: () => locale },
        Image: class { constructor() { images.push(this); } }
    };
    runInNewContext(source, context, { timeout: 1000 });
    function mapper() {
        return Object.assign(Object.create(context.Mapper.prototype), {
            zone: 12, level: 0, floorPins: {}, span: { style: {} },
            overlay: true, zoom: 0, overlaySpan: { style: {} },
            pins: [{ x: 25, y: 50, floor: 0 }], nCoords: 1
        });
    }
    return { images, mapper };
}

for (const locale of locales) {
    const { images, mapper } = fixture(locale);
    const map = mapper();
    map.setMap('12', 0);
    equal(map.span.style.background, background(locale, '12'), locale + ': active locale is tried first');
    equal(images.length, locale === 'enus' ? 0 : 1, 'English does not probe or retry itself');
    if (locale === 'enus') continue;
    equal(images.at(-1).src, url(locale, '12'), 'Probe matches the CSS image');
    images.at(-1).onload();
    equal(map.span.style.background, background(locale, '12'), 'Available localized image is retained');

    map.setMap('13', 0);
    images.at(-1).onerror();
    equal(map.span.style.background, background('enus', '13'), 'Missing base image uses English');
    equal(map.span.style.backgroundSize, 'contain', 'Fallback keeps map scaling');
    equal(map.overlaySpan.style.background, 'url(/static/images/wow/maps/overlay/normal/13.png)', 'Overlay is independent of language');
    equal(map.pins, [{ x: 25, y: 50, floor: 0 }], 'Fallback preserves pins');
    equal(map.nCoords, 1, 'Fallback preserves coordinate count');

    map.zone = 2557;
    map.setMap('2557-0', 0);
    images.at(-1).onload();
    map.setMap('2557-1', 1);
    const requests = images.length;
    images.at(-1).onerror();
    equal(map.span.style.background, background('enus', '2557-1'), 'Missing individual floor uses matching English floor');
    equal(map.level, 1, 'Fallback preserves selected floor');
    equal(images.length, requests, 'Fallback creates no recursive image probe');

    map.setMap('2557-0', 0);
    images.at(-1).onload();
    equal(map.span.style.background, background(locale, '2557-0'), 'Switching back retains the available localized floor');
}

{
    const { images, mapper } = fixture('frfr');
    const map = mapper();
    map.mapLocales = { 12: 'dede' };
    map.setMap('12', 0);
    equal(images.at(-1).src, url('dede', '12'), 'Explicit localized override takes precedence');
    images.at(-1).onerror();
    equal(map.span.style.background, background('enus', '12'), 'Unavailable explicit override also falls back');
    map.mapLocales = { 12: 'enus' };
    const count = images.length;
    map.setMap('12', 0);
    equal(images.length, count, 'Explicit English override needs no probe');
    equal(map.span.style.background, background('enus', '12'), 'Explicit English override is retained');
}

{
    const { images, mapper } = fixture('frfr');
    const map = mapper();
    map.setMap('12', 0);
    const oldImage = images.at(-1), oldError = oldImage.onerror, oldLoad = oldImage.onload;
    map.zone = 2557;
    map.setMap('2557-0', 0);
    const currentImage = images.at(-1);
    equal(oldImage.onerror, null, 'Zone change detaches obsolete image handlers');
    oldLoad(); oldError();
    equal(map.span.style.background, background('frfr', '2557-0'), 'Already queued events cannot replace a new zone');
    currentImage.onerror();
    equal(map.span.style.background, background('enus', '2557-0'), 'Stale load cannot cancel current fallback');

    map.setMap('2557-0', 0);
    const oldFloorError = images.at(-1).onerror;
    map.setMap('2557-1', 1);
    images.at(-1).onload();
    oldFloorError();
    equal(map.span.style.background, background('frfr', '2557-1'), 'Late failure cannot replace a successful new floor');

    map.setMap('2557-0', 0);
    const lateError = images.at(-1).onerror;
    map.mapLocales = { 2557: 'enus' };
    map.setMap('2557-1', 1);
    lateError();
    equal(map.span.style.background, background('enus', '2557-1'), 'Late failure cannot replace an explicit English selection');

    const popup = mapper();
    popup.setMap('12', 0);
    const popupError = images.at(-1).onerror;
    map.mapLocales = {};
    map.setMap('2557-0', 0);
    images.at(-1).onload();
    popupError();
    equal(popup.span.style.background, background('enus', '12'), 'Independent popup falls back');
    equal(map.span.style.background, background('frfr', '2557-0'), 'Other mapper retains its localized map');
}
console.log(`PASS: ${checks} shared map locale, floor, override and asynchronous fallback checks`);

if (process.argv.includes('--browser')) {
    const { createServer } = await import('node:http');
    const { browser } = await import('./browser-cdp.mjs');
    const pixel = Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64');
    const requests = [];
    const server = createServer((req, res) => {
        if (req.url === '/mapper.js') {
            res.writeHead(200, { 'Content-Type': 'application/javascript' }); res.end(source); return;
        }
        if (req.url.startsWith('/static/')) {
            requests.push(req.url);
            const match = req.url.match(/\/maps\/(\w+)\/original\/(.+)\.jpg$/);
            const exists = match && (['14', '2557-0'].includes(match[2]) ||
                (match[1] === 'enus' && ['12', '2557-1'].includes(match[2])));
            res.writeHead(exists ? 200 : 404, { 'Content-Type': exists ? 'image/gif' : 'text/plain', 'Cache-Control': 'no-store' });
            res.end(exists ? pixel : 'missing map'); return;
        }
        res.writeHead(200, { 'Content-Type': 'text/html' });
        res.end(`<!doctype html><div id="map" style="width:488px;height:325px"></div>
            <script src="/mapper.js"></script><script>
            var g_staticUrl='/static', activeLocale='enus', Locale={getName:()=>activeLocale};
            var map=Object.assign(Object.create(Mapper.prototype),{
                zone:12,level:0,floorPins:{},span:document.getElementById('map')
            });
            function selectMap(locale,image,level=0) { activeLocale=locale;map.setMap(image,level); }
            </script>`);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    let page;
    const start = checks;
    try {
        page = await browser();
        await page.goto('http://127.0.0.1:' + server.address().port);
        for (const locale of locales) {
            for (const [image, expected, level] of [['12', 'enus', 0], ['14', locale, 0], ['2557-0', locale, 0], ['2557-1', 'enus', 1]]) {
                const before = requests.length;
                await page.evaluate(`selectMap(${JSON.stringify(locale)},${JSON.stringify(image)},${level})`);
                // Wait for actual loading, not just the synchronous CSS assignment.
                await page.waitFor(`(async()=>{
                    if(map.mapImage) return false;
                    const image=new Image();image.src=getComputedStyle(map.span).backgroundImage.slice(5,-2);
                    try {await image.decode();return image.naturalWidth>0;} catch {return false;}
                })()`, { description: locale + '/' + image + ' has a decoded background image' });
                const state = await page.evaluate(`({background:map.span.style.backgroundImage,size:map.span.style.backgroundSize,level:map.level})`);
                equal(state.background, `url("${url(expected, image)}")`, 'Chromium displays the localized/English image');
                equal(state.size, 'contain', 'Chromium keeps the image scaled to the map');
                equal(state.level, level, 'Chromium retains floor selection');
                equal(requests.slice(before).includes(url(locale, image)), true, 'Browser requests the selected locale first');
                if (locale !== 'enus' && expected === locale)
                    equal(requests.slice(before).includes(url('enus', image)), false, 'Available localized image does not request English');
            }
        }
        await page.evaluate(`selectMap('frfr','9999')`);
        await page.waitFor(`!map.mapImage && map.span.style.backgroundImage.includes('/enus/original/9999.jpg')`);
        await page.waitFor(`performance.getEntriesByType('resource').some(r=>r.name.endsWith('/enus/original/9999.jpg'))`);
        const count = requests.length;
        await page.sleep(250);
        equal(requests.length, count, 'Missing English image does not cause a request loop');
        equal(requests.filter(path => path === url('enus', '9999')).length, 1, 'English fallback is requested once');
        equal(page.errors, [], 'No JavaScript exceptions in Chromium');
        console.log(`PASS: ${checks - start} Chromium map image loading, HTTP fallback and floor checks`);
    } finally {
        await page?.close(); server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
    }
}
