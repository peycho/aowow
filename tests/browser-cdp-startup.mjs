// Force Chrome's endpoint file to exist before it contains a usable port.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {browser} from './browser-cdp.mjs';

const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'aowow-browser-startup-'));
const wrapper = path.join(directory, 'chrome');
const saved = {chrome:process.env.AOWOW_STARTUP_TEST_CHROME, content:process.env.AOWOW_STARTUP_TEST_CONTENT};
process.env.AOWOW_STARTUP_TEST_CHROME = process.env.AOWOW_TEST_CHROME || 'google-chrome';
fs.writeFileSync(wrapper, `#!/bin/sh
for argument do
    case "$argument" in --user-data-dir=*) profile=\${argument#--user-data-dir=} ;; esac
done
printf '%s' "$AOWOW_STARTUP_TEST_CONTENT" > "$profile/DevToolsActivePort"
sleep 0.3
exec "$AOWOW_STARTUP_TEST_CHROME" "$@"
`, {mode:0o700});
let page, checks = 0;
try {
    for (const content of ['', '12345\n']) {
        process.env.AOWOW_STARTUP_TEST_CONTENT = content;
        // The development runner deliberately mounts /tmp with noexec.
        page = await browser('/bin/sh', [wrapper]);
        assert.equal(await page.evaluate('location.href'), 'about:blank', 'Incomplete endpoint file must not be used as a port');
        ++checks;
        assert.equal(await page.evaluate('6 * 7'), 42, 'The eventual real Chrome endpoint accepts DevTools commands');
        ++checks;
        await page.close(); page = null;
    }
    fs.writeFileSync(wrapper, '#!/bin/sh\nexit 7\n', {mode:0o700});
    await assert.rejects(browser('/bin/sh', [wrapper]), /Chrome exited before debugging was ready/);
    ++checks;
    console.log(`PASS: ${checks} browser startup, incomplete endpoint and early-exit checks`);
}
finally {
    await page?.close(); fs.rmSync(directory, {recursive:true, force:true});
    for (const [key, value] of [['AOWOW_STARTUP_TEST_CHROME', saved.chrome], ['AOWOW_STARTUP_TEST_CONTENT', saved.content]]) {
        if (value === undefined) delete process.env[key]; else process.env[key] = value;
    }
}
