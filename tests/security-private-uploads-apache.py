#!/usr/bin/env python3
"""Prepare a synthetic Apache tree or verify its real HTTP upload denial rules."""
import argparse
import http.client
from pathlib import Path
import shutil
import tempfile
from urllib.parse import urlsplit

parser = argparse.ArgumentParser()
parser.add_argument('--allow-override', choices=['All', 'Options FileInfo'], default='Options FileInfo')
parser.add_argument('--multiviews', action='store_true', help='Exercise inherited content negotiation.')
parser.add_argument('--cgi-entrypoint', action='store_true', help='Exercise a synthetic executable CGI entrypoint.')
mode = parser.add_mutually_exclusive_group(required=True)
mode.add_argument('--prepare', type=Path)
mode.add_argument('--check')
args = parser.parse_args()
repo = Path(__file__).resolve().parent.parent

if args.prepare:
    root = args.prepare.resolve()
    if root.parent != Path(tempfile.gettempdir()).resolve() or not root.name.startswith('aowow-private-uploads-apache-'):
        parser.error('Use an empty aowow-private-uploads-apache-* directory in the temporary directory.')
    if root.exists() and any(root.iterdir()):
        parser.error('Fixture directory must be empty.')
    root.mkdir(exist_ok=True)
    root.chmod(0o755)
    files = {
        'uploads/screenshots/pending/7.jpg': 'PRIVATE-STAGING-SENTINEL',
        'uploads/screenshots/temp/owner-1-1-key.jpg': 'PRIVATE-STAGING-SENTINEL',
        'uploads/screenshots/temp/owner-1-1-key_original.jpg': 'PRIVATE-STAGING-SENTINEL',
        'uploads/temp/owner-1-1-video': 'PRIVATE-STAGING-SENTINEL',
        'uploads/temp/owner-avatar-12-key.jpg': 'PRIVATE-STAGING-SENTINEL',
        'uploads/temp/owner-avatar-12-key_original.jpg': 'PRIVATE-STAGING-SENTINEL',
        'uploads/temp/rejected.png': 'PRIVATE-STAGING-SENTINEL',
        'uploads/temp/deep/upload.php': 'PRIVATE-STAGING-SENTINEL',
        'uploads/screenshots/normal/7.jpg': 'PUBLIC-APPROVED-SENTINEL',
        'uploads/screenshots/thumb/7.jpg': 'PUBLIC-APPROVED-SENTINEL',
        'uploads/screenshots/resized/7.jpg': 'PUBLIC-APPROVED-SENTINEL',
        'uploads/avatars/12.jpg': 'PUBLIC-APPROVED-SENTINEL',
        'uploads/guide/images/1.png': 'PUBLIC-APPROVED-SENTINEL',
        'js/public.js': 'PUBLIC-APPROVED-SENTINEL',
        'css/public.css': 'PUBLIC-APPROVED-SENTINEL',
        'widgets/power.js': 'PUBLIC-APPROVED-SENTINEL',
        'wowsounds/123': 'PUBLIC-APPROVED-SENTINEL',
    }
    denied_files = [
        '.secret', '.hidden/public.txt', '.git/config', 'images/.hidden/public.txt',
        'probe.php', 'probe.PHP', 'probe.php5', 'probe.php.jpg', 'probe.phtml',
        'probe.pht', 'probe.phar', 'probe.cgi', 'probe.pl', 'probe.py',
        'probe.sh', 'probe.shtml', 'uploads/guide/images/probe.php.jpg',
        'uploads/.hidden/public.txt', 'uploads/avatars/probe.phtml',
    ]
    files.update({name: 'PRIVATE-STAGING-SENTINEL' for name in denied_files})
    for name, content in files.items():
        path = root / 'site/static' / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content)
    shutil.copyfile(repo / '.htaccess', root / 'site/.htaccess')
    shutil.copyfile(repo / 'static/.htaccess', root / 'site/static/.htaccess')
    shutil.copyfile(repo / 'static/uploads/.htaccess', root / 'site/static/uploads/.htaccess')
    (root / 'site/index.php').write_text('ROOT-ROUTING-SENTINEL')
    if args.cgi_entrypoint:
        (root / 'site/index.php').write_text('#!/bin/sh\nprintf "Content-Type: text/html; charset=utf-8\\r\\n\\r\\nROOT-ROUTING-SENTINEL"\n')
        (root / 'site/index.php').chmod(0o755)
    shutil.copyfile(repo / 'crossdomain.xml', root / 'site/crossdomain.xml')
    shutil.copytree(root / 'site/static', root / 'assets')
    shutil.copytree(root / 'site/static/uploads', root / 'upload-assets')
    for name in ['config/config.php', 'includes/kernel.php', 'cache/private.txt', 'tests/private.txt', '.git/config', 'other.php']:
        path = root / 'site' / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text('PRIVATE-STAGING-SENTINEL')
    (root / 'site/robots.txt').write_text('PUBLIC-APPROVED-SENTINEL')
    shutil.copyfile(repo / 'crossdomain.xml', root / 'assets/crossdomain.xml')
    (root / 'httpd.conf').write_text('''ServerRoot "/usr/local/apache2"
Listen 8080
ServerName app.example
PidFile /tmp/aowow-httpd.pid
LoadModule mpm_event_module modules/mod_mpm_event.so
LoadModule unixd_module modules/mod_unixd.so
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule mime_module modules/mod_mime.so
LoadModule dir_module modules/mod_dir.so
LoadModule alias_module modules/mod_alias.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadModule negotiation_module modules/mod_negotiation.so
__CGI_MODULE__
User #65534
Group #65534
TypesConfig conf/mime.types
ErrorLog /proc/self/fd/2
LogLevel warn
DocumentRoot /fixture/site
<Directory /fixture>
    Require all granted
    AllowOverride __ALLOW_OVERRIDE__
    Options FollowSymLinks __MULTIVIEWS__ __CGI_OPTION__
    __CGI_HANDLER__
</Directory>
# Exercise an inherited script handler: asset directories must override it.
<Directory /fixture/site/static>
    SetHandler application/x-httpd-php
</Directory>
<Directory /fixture/assets>
    SetHandler application/x-httpd-php
</Directory>
<Directory /fixture/upload-assets>
    SetHandler application/x-httpd-php
</Directory>
<FilesMatch "^\\.ht">
    Require all denied
</FilesMatch>
<VirtualHost *:8080>
    ServerName app.example
    DocumentRoot /fixture/site
    Alias /nested /fixture/site
</VirtualHost>
<VirtualHost *:8080>
    ServerName static.example
    DocumentRoot /fixture/assets
</VirtualHost>
<VirtualHost *:8080>
    ServerName uploads.example
    DocumentRoot /fixture/upload-assets
</VirtualHost>
'''.replace('__ALLOW_OVERRIDE__', args.allow_override)
   .replace('__MULTIVIEWS__', 'MultiViews' if args.multiviews else '')
   .replace('__CGI_MODULE__', 'LoadModule cgid_module modules/mod_cgid.so\nScriptSock /tmp/aowow-cgid' if args.cgi_entrypoint else '')
   .replace('__CGI_OPTION__', 'ExecCGI' if args.cgi_entrypoint else '')
   .replace('__CGI_HANDLER__', 'AddHandler cgi-script .php' if args.cgi_entrypoint else ''))
    print('PASS: synthetic Apache fixture prepared')
    raise SystemExit

base = urlsplit(args.check)
if base.scheme != 'http' or base.hostname not in ('127.0.0.1', 'localhost', 'apache'):
    parser.error('Check only the isolated loopback/Apache fixture.')
checks = 0

def request(path, host, method='GET', headers=None):
    connection = http.client.HTTPConnection(base.hostname, base.port or 8080, timeout=5)
    connection.request(method, path, headers={'Host': host, **(headers or {})})
    response = connection.getresponse()
    result = response.status, response.read(), dict(response.getheaders())
    connection.close()
    return result

def check(condition, message):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)

private = [
    'uploads/screenshots/pending/7.jpg',
    'uploads/screenshots/temp/owner-1-1-key.jpg',
    'uploads/screenshots/temp/owner-1-1-key_original.jpg',
    'uploads/temp/owner-1-1-video',
    'uploads/temp/owner-avatar-12-key.jpg',
    'uploads/temp/owner-avatar-12-key_original.jpg',
    'uploads/temp/rejected.png', 'uploads/temp/deep/upload.php',
    'uploads/screenshots/pend%69ng/7.jpg',
    'uploads/%74emp/owner-1-1-video',
    'uploads/screenshots/normal/../pending/7.jpg',
    'uploads/screenshots/pending/7.jpg/extra',
    'uploads/screenshots/pending', 'uploads/screenshots/temp/', 'uploads/temp/',
    'uploads/temp/owner-1-1-video?download=1',
]
public = [
    'uploads/screenshots/normal/7.jpg', 'uploads/screenshots/thumb/7.jpg',
    'uploads/screenshots/resized/7.jpg', 'uploads/avatars/12.jpg',
    'uploads/guide/images/1.png', 'js/public.js', 'css/public.css',
    'widgets/power.js', 'wowsounds/123',
]
denied = [
    '.secret', '.hidden/public.txt', '.git/config', 'images/.hidden/public.txt',
    '%2ehidden/public.txt', 'probe.php', 'probe.PHP', 'probe.php5',
    'probe.php.jpg', 'probe.phtml', 'probe.pht', 'probe.phar', 'probe.cgi',
    'probe.pl', 'probe.py', 'probe.sh', 'probe.shtml', 'probe.php/extra',
    'uploads/guide/images/probe.php.jpg', 'uploads/.hidden/public.txt',
    'uploads/avatars/probe.phtml', 'js/public.js/extra', 'js/',
]

def check_headers(headers, message):
    for name, expected in [('X-Content-Type-Options', 'nosniff'), ('Referrer-Policy', 'same-origin'), ('X-Frame-Options', 'SAMEORIGIN')]:
        check(headers.get(name) == expected, f'{message}: {name}')

for host, prefix in [('app.example', '/static/'), ('app.example', '/nested/static/'), ('static.example', '/')]:
    for path in private + denied:
        for method in ['GET', 'HEAD']:
            status, body, headers = request(prefix + path, host, method, {'Range': 'bytes=0-128'})
            check(status in (403, 404) and b'PRIVATE-STAGING-SENTINEL' not in body,
                  f'{host} {method} {prefix + path}: status {status}')
            check_headers(headers, f'Denied asset: {host} {path}')
    for path in public:
        status, body, headers = request(prefix + path, host)
        check(status == 200 and body == b'PUBLIC-APPROVED-SENTINEL', f'Public asset remains accessible: {host} {path}: {status}')
        check_headers(headers, f'Public asset: {host} {path}')
        if path.endswith('.js'):
            check(headers.get('Content-Type', '').split(';')[0] in ('text/javascript', 'application/javascript', 'application/x-javascript'), 'JavaScript MIME type remains usable with nosniff')
        elif path.endswith('.css'):
            check(headers.get('Content-Type', '').split(';')[0] == 'text/css', 'CSS MIME type remains usable with nosniff')
        check(request(prefix + path, host, 'HEAD')[0] == 200, f'Public HEAD: {host} {path}')
    status, body, _ = request(prefix + 'wowsounds/123', host, headers={'Range': 'bytes=0-5'})
    check(status == 206 and body == b'PUBLIC', f'Extensionless sound Range: {host}')
    for path in ['probe', 'probe.php', 'probe.php.jpg']:
        status, body, _ = request(prefix + path, host)
        check(status in (403, 404, 406) and b'PRIVATE-STAGING-SENTINEL' not in body, f'Negotiated script denied: {host} {path}: {status}')

for path in private + [p for p in denied if p.startswith('uploads/')]:
    status, body, headers = request('/' + path.removeprefix('uploads/'), 'uploads.example')
    check(status in (403, 404) and b'PRIVATE-STAGING-SENTINEL' not in body, f'Separate uploads root: {path}')
    check_headers(headers, f'Separate uploads root: {path}')
for path in [p for p in public if p.startswith('uploads/')]:
    status, body, headers = request('/' + path.removeprefix('uploads/'), 'uploads.example')
    check(status == 200 and body == b'PUBLIC-APPROVED-SENTINEL', f'Separate public uploads: {path}')
    check_headers(headers, f'Separate public uploads: {path}')

status, body, _ = request('/?upload=preview&kind=pending&id=7', 'app.example')
check(status == 200 and body == b'ROOT-ROUTING-SENTINEL', 'Root PHP routing remains available (execution is covered separately by PHP HTTP checks)')
queries = ['', '?item=19019', '?search=Thunderfury', '?item=19019&power', '?search=sword&json', '?sound=123&playlist', '?account=signin', '?admin=siteconfig', '?upload=guide', '?go-to-comment=7', '?items&filter=na%3Dsword&locale=2', '?%69tem=19019', '?&item=19019']
for prefix in ['/', '/nested/', '/index.php', '/nested/index.php']:
    for query in queries:
        for method in ['GET', 'HEAD', 'POST']:
            status, body, headers = request(prefix + query, 'app.example', method)
            check(status == 200 and (method == 'HEAD' or body == b'ROOT-ROUTING-SENTINEL'), f'Application routing: {method} {prefix}{query}: {status}')
            check_headers(headers, f'Application routing: {method} {prefix}{query}')
for prefix in ['/', '/nested/']:
    for path in ['config/config.php', 'includes/kernel.php', 'cache/private.txt', 'tests/private.txt', '.git/config', 'other.php', 'index.php/extra']:
        status, body, headers = request(prefix + path, 'app.example')
        check(status in (403, 404) and b'PRIVATE-STAGING-SENTINEL' not in body, f'Private application path: {prefix}{path}')
    status, body, _ = request(prefix + 'robots.txt', 'app.example')
    check(status == 200 and body == b'PUBLIC-APPROVED-SENTINEL', f'robots.txt: {prefix}')
for host, path in [('app.example', '/crossdomain.xml'), ('app.example', '/nested/crossdomain.xml'), ('static.example', '/crossdomain.xml')]:
    status, body, _ = request(path, host)
    check(status == 200 and b'permitted-cross-domain-policies="none"' in body and b'allow-access-from' not in body,
          f'Explicit deny policy remains accessible: {host} {path}')
    check(request(path, host, 'HEAD')[0] == 200, f'Deny policy HEAD works: {host} {path}')
print(f'PASS: {checks} Apache routing, asset, header and upload-denial HTTP checks')
