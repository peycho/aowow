#!/usr/bin/env python3
"""Exercise the real CLI database editor through a Docker-allocated terminal."""
import os
import pty
import select
import subprocess
import sys
import time
import tty

master, slave = pty.openpty()
tty.setraw(slave)
command = sys.argv[1:] + ['run', '--rm', '--no-deps', 'cli', 'php', 'aowow', '--database']
process = subprocess.Popen(command, stdin=slave, stdout=slave, stderr=slave)
os.close(slave)
pending = b''


def expect(prompt):
    global pending
    deadline = time.monotonic() + 30
    while prompt not in pending:
        if time.monotonic() >= deadline:
            raise RuntimeError('Interactive CLI did not reach the expected prompt.')
        if select.select([master], [], [], 0.2)[0]:
            chunk = os.read(master, 65536)
            if not chunk:
                raise RuntimeError('Interactive CLI exited before the expected prompt.')
            pending += chunk
    pending = pending.split(prompt, 1)[1]


try:
    expect(b'select an index')
    os.write(master, b'1\n')  # World connection; AoWoW is index zero.
    for prompt, value in [
        (b'Server Host:', 'trinity-db'),
        (b'User:', 'aowow_reader'),
        (b'Password:', os.environ['WORLD_DB_PASSWORD']),
        (b'Database Name:', 'world'),
        (b'Port (blank: default or legacy host:port):', '3307'),
        (b'Table prefix:', ''),
    ]:
        expect(prompt)
        os.write(master, (value + '\n').encode())
    expect(b'select an index')
    os.write(master, b'\x1b')
    assert process.wait(timeout=15) == 0, 'Interactive CLI failed.'
    print('PASS: interactive CLI terminal input and database port persistence')
finally:
    if process.poll() is None:
        process.terminate()
        process.wait(timeout=10)
    os.close(master)
