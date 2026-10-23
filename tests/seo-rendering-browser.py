"""Exercise real progressive enhancement and a browser with document scripts blocked."""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from html.parser import HTMLParser
from pathlib import Path
import subprocess
import sys
import tempfile
import threading
from urllib.parse import parse_qs, urlsplit

fixture = Path(sys.argv[1]).read_bytes()
browser = sys.argv[2]
home = len(sys.argv) > 3 and sys.argv[3] == "home"


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_GET(self):
        query = parse_qs(urlsplit(self.path).query)
        # basic.js requests these datasets at startup. No scaling rows are
        # needed by the synthetic markup/list fixture; never fetch live data.
        scaling = query.get('data', [''])[0] in ('item-scaling', 'spell-scaling')
        self.send_response(200)
        self.send_header('Content-Type', 'text/javascript' if scaling else 'text/html; charset=utf-8')
        if query.get('scripts') == ['off']:
            self.send_header('Content-Security-Policy', "script-src 'none'")
        self.end_headers()
        self.wfile.write(b'/* Empty synthetic scaling dataset. */' if scaling else fixture)


class InitialContent(HTMLParser):
    def __init__(self):
        super().__init__()
        self.ids = set()
        self.links = []
        self.heading = False

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        self.ids.add(attrs.get('id'))
        if tag == 'a':
            self.links.append(attrs.get('href', ''))
        if tag == 'h2':
            self.heading = True


server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
threading.Thread(target=server.serve_forever, daemon=True).start()
try:
    with tempfile.TemporaryDirectory(prefix='aowow-seo-browser-') as directory:
        for scripting in (True, False):
            command = [browser, '--headless', '--no-sandbox', '--disable-gpu',
                '--disable-dev-shm-usage', '--disable-background-networking',
                '--user-data-dir='+str(Path(directory) / str(scripting))]
            suffix = '' if scripting else '?scripts=off'
            command.extend(['--dump-dom', f'http://127.0.0.1:{server.server_port}/fixture{suffix}'])
            result = subprocess.run(command, capture_output=True, timeout=45)
            if result.returncode:
                raise RuntimeError(result.stderr.decode())
            dom = Path(directory) / 'fixture.dom'
            dom.write_bytes(result.stdout)
            if scripting:
                subprocess.run([sys.executable, 'tests/ci/check-browser.py', str(dom)], check=True)
            else:
                initial = InitialContent()
                initial.feed(result.stdout.decode())
                if home:
                    assert 'server-navigation' in initial.ids and 'home-menu' in initial.ids
                    assert all(link in initial.links for link in ('?items', '?spells'))
                    print('PASS: 2 script-blocked homepage navigation checks')
                    continue
                assert initial.heading and 'article-generic' in initial.ids, (initial.ids, result.stdout[:500], result.stderr[-1000:])
                assert 'server-lists-generic' in initial.ids and 'server-navigation' in initial.ids
                assert all(link in initial.links for link in ('?npc=1', '?npc=2', '?items', '?item=1'))
                assert 'lv-serverfixture' not in initial.ids
                print('PASS: 5 script-blocked initial HTML browser checks')
finally:
    server.shutdown()
    server.server_close()
