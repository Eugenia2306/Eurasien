#!/usr/bin/env python3
import socket
import sys
host = "127.0.0.1"
port = int(sys.argv[1]) if len(sys.argv) > 1 else 5678
s = socket.socket()
s.settimeout(1.5)
try:
    s.connect((host, port))
except OSError:
    sys.exit(1)
finally:
    s.close()
sys.exit(0)
