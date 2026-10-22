// Minimal Chrome DevTools client for real layout and touch regression checks.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn } from 'node:child_process';
export async function browser(binary = process.env.AOWOW_TEST_CHROME || 'google-chrome') {
    const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'aowow-browser-'));
    const chrome = spawn(binary, ['--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-background-networking', '--remote-debugging-port=0', '--user-data-dir=' + profile], { stdio: 'ignore' });
    const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
    let socket, sequence = 0, loaded = false;
    const pending = new Map(), errors = [];
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
        const targets = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
        socket = new WebSocket(targets[0].webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        socket.onmessage = event => {
            const message = JSON.parse(event.data);
            if (message.method === 'Page.loadEventFired') loaded = true;
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
        await call('Page.enable'); await call('Runtime.enable');
        return {
            call, errors, close, sleep,
            async goto(url) {
                loaded = false; errors.length = 0;
                const result = await call('Page.navigate', { url });
                if (result.errorText) throw new Error(result.errorText);
                // Wait outside Runtime.evaluate: this application uses synchronous
                // dataset requests while parsing its head scripts.
                for (let i = 0; i < 300 && !loaded; ++i) await sleep(100);
                if (!loaded) throw new Error('Page load timeout: ' + url);
                await sleep(100);
            },
            async evaluate(expression) {
                const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
                if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
                return result.result.value;
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
