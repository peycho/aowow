"""Serve root/subdirectory browser fixtures using real assets and synthetic local game data."""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import json
import subprocess
import sys
import tempfile
import threading
from urllib.parse import parse_qs, urlsplit

root = Path(__file__).resolve().parent.parent
fixture = Path(sys.argv[1]).read_bytes()
assets = json.loads(Path(sys.argv[2]).read_text())
browser = sys.argv[3]
requests = []


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_GET(self):
        path = urlsplit(self.path).path
        requests.append(path)
        prefix = '/subdir' if path.startswith('/subdir/') else ''
        path = path.removeprefix(prefix)
        kind = 'text/html; charset=utf-8'
        query = parse_qs(urlsplit(self.path).query, keep_blank_values=True)
        if 'power' in query and 'item' in query:
            data = b'$WowheadPower.registerItem(1,0,{name_enus:"Fixture Sword",quality:2,icon:"inv_misc_questionmark",tooltip_enus:"<table><tr><td>Local tooltip fixture</td></tr></table>",map:{},spells:{}});'
            kind = 'text/javascript'
        elif 'power' in query and 'npc' in query:
            data = b'$WowheadPower.registerNpc(1,0,{name_enus:"Fixture NPC",tooltip_enus:"<table><tr><td>Fixture NPC</td></tr></table>",map:{},spells:{}});'
            kind = 'text/javascript'
        elif 'power' in query and 'spell' in query:
            data = b'$WowheadPower.registerSpell(1,0,{name_enus:"Fixture Pet",tooltip_enus:"<table><tr><td>Fixture Pet</td></tr></table>",map:{},spells:{}});'
            kind = 'text/javascript'
        elif 'data' in query:
            subject = 3 if query['data'][0] == 'item-scaling' else 6
            data = f'if (window.$WowheadPower) $WowheadPower.loadScales({subject},0);'.encode()
            kind = 'text/javascript'
        elif path == '/fixture':
            data = fixture
        elif path == '/widget':
            origin = f'http://127.0.0.1:{self.server.server_port}'
            power = assets['static/widgets/power.js'].replace('http://127.0.0.1', origin).replace("'/static'", f"'{prefix}/static'")
            # The real remote widget bootstraps basic.js and basic.css from configured local hosting.
            data = ('''<!doctype html><meta charset="UTF-8"><pre id="result">RUNNING</pre>
<a id="link" href="?item=1" data-wowhead="item=1">Item #1</a>
<script>var aowow_tooltips={renamelinks:true,iconizelinks:true,colorlinks:true};</script>
<script>''' + power.replace('</script', '<\\/script') + '''</script>
<script>
setTimeout(function(){
 var a=document.getElementById('link');
 a.dispatchEvent(new MouseEvent('mouseover',{bubbles:true,clientX:10,clientY:10}));
 document.getElementById('result').textContent=(a.textContent==='Fixture Sword' &&
 a.classList.contains('icontinyl') && document.body.textContent.includes('Local tooltip fixture')) ? 'PASS' : 'FAIL';
},500);
</script>''').encode()
        elif path.startswith('/static/'):
            file = (root / path.lstrip('/')).resolve()
            if not file.is_relative_to(root / 'static'):
                self.send_error(404)
                return
            if file.is_file():
                data = file.read_bytes()
            elif file.suffix in ('.gif', '.png', '.jpg'):
                # Extracted game art is operator-owned; supply a local placeholder in this source-only fixture.
                data = (root / 'static/images/ui/misc/selection-h.gif').read_bytes()
            else:
                self.send_error(404)
                return
            kind = {'.js': 'text/javascript', '.css': 'text/css', '.gif': 'image/gif',
                    '.png': 'image/png', '.jpg': 'image/jpeg'}.get(file.suffix, 'application/octet-stream')
        else:
            self.send_error(404)
            return
        self.send_response(200)
        self.send_header('Content-Type', kind)
        self.send_header('Content-Security-Policy', "default-src 'self' data:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'")
        self.end_headers()
        self.wfile.write(data)


server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
threading.Thread(target=server.serve_forever, daemon=True).start()
try:
    with tempfile.TemporaryDirectory(prefix='aowow-retirement-browser-') as directory:
        for prefix in ['', '/subdir']:
            url = f'http://127.0.0.1:{server.server_port}{prefix}/fixture'
            result = subprocess.run([browser, '--headless', '--no-sandbox', '--disable-gpu',
                '--user-data-dir='+str(Path(directory) / ('subdir' if prefix else 'root')),
                '--disable-dev-shm-usage', '--disable-background-networking', '--virtual-time-budget=10000',
                '--host-resolver-rules=MAP *.wowhead.com ~NOTFOUND, MAP *.zamimg.com ~NOTFOUND, MAP *.zam.com ~NOTFOUND',
                '--dump-dom', url], capture_output=True, timeout=45)
            if result.returncode:
                raise RuntimeError(result.stderr.decode())
            dom = Path(directory) / 'fixture.dom'
            dom.write_bytes(result.stdout)
            validation = subprocess.run([sys.executable, str(root / 'tests/ci/check-browser.py'), str(dom)])
            if validation.returncode:
                print(result.stderr.decode()[-3000:], file=sys.stderr)
                print(result.stdout.decode()[:1500], file=sys.stderr)
                raise RuntimeError('Retirement browser fixture failed')
            for image in ['selection-h.gif', 'selection-v.gif']:
                if f'{prefix}/static/images/ui/misc/{image}' not in requests:
                    raise RuntimeError('Crop border was not delivered: '+prefix+'/'+image)
finally:
    server.shutdown()
    server.server_close()
