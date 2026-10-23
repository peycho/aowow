"""Idle HTTP/1.1 asset connections must not exhaust the tiny dev worker pool."""
import http.client
import sys
import time
from urllib.parse import urlsplit

url = urlsplit(sys.argv[1])
workers = int(sys.argv[2])
connections = []
try:
    # Each response is fully consumed, leaving only an idle keep-alive socket.
    # With the old prefork config these sockets occupied every Apache worker.
    for _ in range(workers):
        connection = http.client.HTTPConnection(url.hostname, url.port, timeout=3)
        connections.append(connection)
        connection.request('GET', url.path, headers={'Connection': 'keep-alive'})
        response = connection.getresponse()
        assert response.status == 200, response.status
        response.read()
    started = time.monotonic()
    connection = http.client.HTTPConnection(url.hostname, url.port, timeout=2)
    connections.append(connection)
    connection.request('GET', url.path)
    response = connection.getresponse()
    assert response.status == 200, response.status
    response.read()
    print(f'PASS: development asset request survives {workers} idle connections '
          f'({time.monotonic() - started:.3f}s)')
finally:
    for connection in connections:
        connection.close()
