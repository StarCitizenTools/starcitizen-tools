# -*- coding: utf-8 -*-
"""
  eventlogging.streams
  ~~~~~~~~~~~~~~~~~~~~

  This module provides helpers for reading from and writing to ZeroMQ
  data streams using ZeroMQ or UDP.

"""
from __future__ import unicode_literals

import io
import json
import re
import socket

import zmq

from .compat import items


__all__ = ('iter_json', 'iter_unicode', 'make_canonical', 'pub_socket',
           'stream', 'sub_socket', 'udp_socket')

# High water mark. The maximum number of outstanding messages to queue
# in memory for any single peer that the socket is communicating with.
ZMQ_HIGH_WATER_MARK = 3000

# If a socket is closed before all its messages has been sent, ZeroMQ
# will wait up to this many miliseconds before discarding the messages.
# We'd rather fail fast, even at the cost of dropping a few events.
ZMQ_LINGER = 0

# The maximum socket buffer size in bytes. This is used to set either
# SO_SNDBUF or SO_RCVBUF for the underlying socket, depending on its
# type. We set it to 64 kB to match Udp2LogConfig::BLOCK_SIZE.
SOCKET_BUFFER_SIZE = 64 * 1024


def pub_socket(endpoint):
    """Get a pre-configured ZeroMQ publisher."""
    context = zmq.Context.instance()
    sock = context.socket(zmq.PUB)
    if hasattr(zmq, 'HWM'):
        sock.hwm = ZMQ_HIGH_WATER_MARK
    sock.linger = ZMQ_LINGER
    sock.sndbuf = SOCKET_BUFFER_SIZE
    canonical_endpoint = make_canonical(endpoint, host='*')
    sock.bind(canonical_endpoint)
    return sock


def sub_socket(endpoint, identity='', subscribe=''):
    """Get a pre-configured ZeroMQ subscriber."""
    context = zmq.Context.instance()
    sock = context.socket(zmq.SUB)
    if hasattr(zmq, 'HWM'):
        sock.hwm = ZMQ_HIGH_WATER_MARK
    sock.linger = ZMQ_LINGER
    sock.rcvbuf = SOCKET_BUFFER_SIZE
    if identity and hasattr(sock, 'identity'):
        sock.identity = identity.encode('utf-8')
    canonical_endpoint = make_canonical(endpoint)
    sock.connect(canonical_endpoint)
    sock.subscribe = subscribe.encode('utf-8')
    return sock


def udp_socket(hostname, port):
    """Parse a URI and configure a UDP socket for it."""
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.bind((hostname, port))
    return sock


def iter_file(file):
    """Wrap a file object's underlying file descriptor with a UTF8 line
    reader and read successive lines."""
    with io.open(file.fileno(), mode='rt', encoding='utf8',
                 errors='replace') as f:
        for line in f:
            yield line


def iter_unicode(stream):
    """Iterator; read and decode unicode strings from a stream."""
    if hasattr(stream, 'recv_unicode'):
        return iter(stream.recv_unicode, None)
    elif hasattr(stream, 'fileno'):
        return iter_file(stream)
    else:
        return (line.decode('utf-8', 'replace') for line in stream)


def iter_json(stream):
    """Iterator; read and decode successive JSON objects from a stream."""
    if hasattr(stream, 'recv_json'):
        return iter(stream.recv_json, None)
    return (json.loads(dgram) for dgram in iter_unicode(stream))


def stream(s, raw=False):
    """Convenience method for getting a JSON-based or line-based
    streaming iterator."""
    return iter_unicode(s) if raw else iter_json(s)


def make_canonical(uri, protocol='tcp', host='127.0.0.1'):
    """Convert a partial endpoint URI to a fully canonical one, using
    TCP and localhost as the default protocol and host. The partial URI
    must at minimum contain a port number."""
    fragments = dict(protocol=protocol, host=host)
    match = re.match(r'((?P<protocol>[^:]+)://)?((?P<host>[^:]+):)?'
                     r'(?P<port>\d+)(?:\?.*)?', '%s' % uri)
    fragments.update((k, v) for k, v in items(match.groupdict()) if v)
    return '%(protocol)s://%(host)s:%(port)s' % dict(fragments)
