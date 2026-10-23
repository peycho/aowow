// Minimal Chrome DevTools client for real layout and touch regression checks.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn } from 'node:child_process';
export async function browser(binary = process.env.AOWOW_TEST_CHROME || 'google-chrome') {
    const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'aowow-browser-'));
    const chrome = spawn(binary, ['--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { stdio: 'ignore' });
    const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
    let socket, sequence = 0;
    const pending = new Map(), errors = [], loads = new Set();
    async function close() {
        socket?.close(); chrome.kill('SIGTERM');
        for (let i = 0; i < 50 && chrome.exitCode === null && chrome.signalCode === null; ++i) await sleep(20);
        if (chrome.exitCode === null && chrome.signalCode === null) chrome.kill('SIGKILL');
        fs.rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 50 });
    }
    try {
        for (let i = 0; i < 200 && !fs.existsSync(profile + '/DevToolsActivePort'); ++i) {
            if (chrome.exitCode !== null) throw new Error('Chrome exited before debugging was ready');
            await sleep(50);
        }
        const port = fs.readFileSync(profile + '/DevToolsActivePort', 'utf8').split('\n')[0];
        // Own the test page instead of racing Chrome's first startup target.
        const response = await fetch('http://127.0.0.1:' + port + '/json/new?about:blank', { method: 'PUT' });
        if (!response.ok) throw new Error('Cannot create browser test page: HTTP ' + response.status);
        const target = await response.json();
        socket = new WebSocket(target.webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        socket.onmessage = event => {
            const message = JSON.parse(event.data);
            if (message.method === 'Page.lifecycleEvent' && message.params.name === 'load')
                loads.add(message.params.frameId + ':' + message.params.loaderId);
            if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
            if (!message.id) return;
            const task = pending.get(message.id);
            if (!task) return;
            clearTimeout(task.timer); pending.delete(message.id);
            if (message.error) task.reject(new Error(JSON.stringify(message.error)));
            else task.resolve(message.result);
        };
        function call(method, params = {}) {
            return new Promise((resolve, reject) => {
                const id = ++sequence;
                const timer = setTimeout(() => { pending.delete(id); reject(new Error('CDP timeout: ' + method)); }, 30000);
                pending.set(id, { resolve, reject, timer }); socket.send(JSON.stringify({ id, method, params }));
            });
        }
        async function evaluate(expression) {
            const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        }
        await call('Page.enable'); await call('Runtime.enable');
        await call('Page.setLifecycleEventsEnabled', { enabled: true });
        return {
            call, errors, close, sleep,
            async goto(url) {
                loads.clear(); errors.length = 0;
                const result = await call('Page.navigate', { url });
                if (result.errorText) throw new Error('Navigation failed: ' + url + ': ' + result.errorText);
                // Wait outside Runtime.evaluate: this application uses synchronous
                // dataset requests while parsing its head scripts. A previous
                // document can finish loading while this request awaits headers;
                // only this frame/loader pair establishes completion. The event
                // may arrive before the navigate response, so retain early loads.
                // Same-document navigation has no new loader or load event.
                if (result.loaderId) {
                    const key = result.frameId + ':' + result.loaderId;
                    for (let i = 0; i < 300 && !loads.has(key); ++i) await sleep(100);
                    if (!loads.has(key)) throw new Error('Page load timeout: ' + url + ' (loader ' + result.loaderId + ')');
                }
                await sleep(100);
            },
            evaluate,
            async waitFor(expression, { timeout = 10000, description = expression } = {}) {
                const deadline = Date.now() + timeout;
                do {
                    try { if (await evaluate(expression) === true) return; }
                    catch (error) {
                        // Navigation can replace the execution context between polls.
                        if (!error.message.startsWith('{') || !/Execution context was destroyed|Cannot find (?:default execution context|context with specified id)/i.test(error.message)) throw error;
                    }
                    await sleep(25);
                } while (Date.now() < deadline);
                throw new Error('Timed out waiting for: ' + description);
            },
            async viewport(width, mobile = width < 980) {
                await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile });
                await call('Emulation.setTouchEmulationEnabled', { enabled: mobile, maxTouchPoints: 1 });
            },
            async tap(x, y) {
                await call('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
                await call('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
                await sleep(100);
            }
        };
    } catch (error) { await close(); throw error; }
}
