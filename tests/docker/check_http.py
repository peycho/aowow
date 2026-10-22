#!/usr/bin/env python3
"""Check the actual production vhost, including inherited upload protections."""
import sys
import urllib.error
import urllib.request

origin = sys.argv[1]
checks = 0


def request(path):
    try:
        with urllib.request.urlopen(origin + path, timeout=10) as response:
            return response.status, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.read()


for path in ["/robots.txt", "/crossdomain.xml", "/favicon.ico",
             "/static/js/basic.js", "/static/widgets/power/power.css",
             "/static/uploads/docker-smoke.txt"]:
    status, body = request(path)
    assert status == 200 and body, (path, status)
    checks += 1

for path in ["/config/config.php", "/config/security.php", "/.env",
             "/includes/kernel.php", "/setup/sql/01-db_structure.sql",
             "/cache/docker-smoke", "/datasets/docker-smoke",
             "/static/uploads/docker-smoke.php", "/static/uploads/docker-smoke.php.txt",
             "/static/uploads/.hidden/probe.txt",
             "/static/uploads/temp/probe.txt",
             "/static/uploads/screenshots/pending/probe.txt"]:
    status, body = request(path)
    assert status == 403, (path, status)
    assert b"PRIVATE-SMOKE" not in body, path
    checks += 1

status, body = request("/")
assert status in (200, 503) and b"<?php" not in body and len(body) > 200, status
checks += 1
print(f"PASS: {checks} Docker HTTP/access checks")
