// Manual, read-only lab sampling of a configured site. Not a CI timing gate.
// Output contains URLs and belongs in ignored reports/ or another private folder.
import fs from 'node:fs';
import path from 'node:path';
import { browser } from './browser-cdp.mjs';

const [baseArgument, output, ...selectedRoutes] = process.argv.slice(2);
if (!baseArgument || !output || baseArgument === '--help') {
    console.log('Usage: node tests/seo-performance-lab.mjs BASE_URL OUTPUT.json [ROUTE ...]\nOptional: AOWOW_PERF_RUNS=3 AOWOW_PERF_WIDTHS=390,1440 AOWOW_PERF_THROTTLE=1');
    process.exit(baseArgument === '--help' ? 0 : 2);
}
const base = new URL(baseArgument);
if (!['http:', 'https:'].includes(base.protocol) || base.search || base.hash || base.username || base.password) throw new Error('Use an HTTP(S) application base URL without credentials, query or fragment');
if (!base.pathname.endsWith('/')) base.pathname += '/';
const runs = Number(process.env.AOWOW_PERF_RUNS || 3);
const widths = (process.env.AOWOW_PERF_WIDTHS || '390,1440').split(',').map(Number);
if (!Number.isInteger(runs) || runs < 1 || runs > 10 || widths.some(n => !Number.isInteger(n) || n < 320 || n > 3840)) throw new Error('Invalid run count or viewport widths');
const routes = selectedRoutes.length ? selectedRoutes : ['', '?item=19019', '?spell=133', '?quest=7786', '?items&page=2', '?guide=lunar-festival'];
const throttle = process.env.AOWOW_PERF_THROTTLE === '1';
const instrumentation = `window.aowowLab={lcp:[],shifts:[],tasks:[],supported:PerformanceObserver.supportedEntryTypes};const identity=e=>e?{tag:e.tagName,id:e.id,class:e.className}:null;for(const [type,key] of [['largest-contentful-paint','lcp'],['layout-shift','shifts'],['longtask','tasks']]){if(!PerformanceObserver.supportedEntryTypes.includes(type))continue;new PerformanceObserver(list=>{for(const e of list.getEntries()){const data=e.toJSON();if(type==='largest-contentful-paint')data.element=identity(e.element);if(type==='layout-shift')data.sources=e.sources.map(s=>({node:identity(s.node),previous:s.previousRect.toJSON(),current:s.currentRect.toJSON()}));window.aowowLab[key].push(data);}}).observe({type,buffered:true});}`;
const report = { schemaVersion: 1, timestamp: new Date().toISOString(), base: base.href, runs, widths, settleMs: 750,
    profile: throttle ? { cpu: 4, latencyMs: 150, downloadBytesPerSecond: 200000, uploadBytesPerSecond: 93750 } : 'unthrottled', samples: [] };
let page;
try {
    page = await browser(); report.browser = await page.call('Browser.getVersion');
    await page.call('Network.enable'); await page.call('Performance.enable');
    await page.call('Page.addScriptToEvaluateOnNewDocument', { source: instrumentation });
    if (throttle) {
        await page.call('Emulation.setCPUThrottlingRate', { rate: 4 });
        await page.call('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 200000, uploadThroughput: 93750, connectionType: 'cellular4g' });
    }
    for (const width of widths) {
        await page.viewport(width);
        for (const route of routes) for (let run = 1; run <= runs; ++run) for (const mode of ['cold', 'warm']) {
            // Browser cache only. Application/session/data caches stay intact.
            if (mode === 'cold') await page.call('Network.clearBrowserCache');
            const url = new URL(route, base);
            if (url.origin !== base.origin || !url.pathname.startsWith(base.pathname)) throw new Error('Keep sampled routes inside the application base');
            await page.goto(url.href); await page.sleep(report.settleMs);
            const data = await page.evaluate(`(()=>{const navigation=performance.getEntriesByType('navigation')[0].toJSON(),resources=performance.getEntriesByType('resource').map(e=>({url:e.name,type:e.initiatorType,start:e.startTime,duration:e.duration,transfer:e.transferSize,encoded:e.encodedBodySize,decoded:e.decodedBodySize}));return {navigation,resources,paint:performance.getEntriesByType('paint').map(e=>e.toJSON()),...window.aowowLab};})()`);
            if (data.navigation.responseStatus >= 400) throw new Error('HTTP ' + data.navigation.responseStatus + ': ' + url.href);
            // Initial-window CLS sessions: at most 5 s with less than 1 s gaps.
            let cls = 0, value = 0, start = 0, previous = 0;
            for (const shift of data.shifts) {
                if (shift.hadRecentInput) continue;
                if (shift.startTime - previous >= 1000 || shift.startTime - start >= 5000) { value = 0; start = shift.startTime; }
                value += shift.value; previous = shift.startTime; cls = Math.max(cls, value);
            }
            const summary = { width, route, run, mode,
                ttfb: data.navigation.responseStart - data.navigation.startTime,
                serverResponseWait: data.navigation.responseStart - data.navigation.requestStart,
                fcp: data.paint.find(e => e.name === 'first-contentful-paint')?.startTime ?? null,
                lcp: data.lcp.at(-1)?.startTime ?? null, cls,
                load: data.navigation.loadEventEnd,
                requests: data.resources.length + 1,
                encodedBytes: data.navigation.encodedBodySize + data.resources.reduce((sum, e) => sum + e.encoded, 0),
                transferBytes: data.navigation.transferSize + data.resources.reduce((sum, e) => sum + e.transfer, 0) };
            const metrics = (await page.call('Performance.getMetrics')).metrics;
            report.samples.push({ ...summary, navigation: data.navigation, resources: data.resources, paint: data.paint,
                observations: { lcp: data.lcp, shifts: data.shifts, tasks: data.tasks, supported: data.supported }, metrics, errors: [...page.errors] });
            fs.mkdirSync(path.dirname(output), { recursive: true }); fs.writeFileSync(output, JSON.stringify(report, null, 2) + '\n');
            console.log(JSON.stringify({ ...summary, errors: page.errors }));
            if (page.errors.length) throw new Error('Browser exceptions: ' + page.errors.join(' | '));
        }
    }
} finally { await page?.close(); }
