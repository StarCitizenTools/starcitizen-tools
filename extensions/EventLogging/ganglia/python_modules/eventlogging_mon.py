#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
  EventLogging Ganglia module
  ~~~~~~~~~~~~~~~~~~~~~~~~~~~

  This is a gmond metric-gathering module which reports a cumulative
  count of messages published by EventLogging ZeroMQ publishers.

  :copyright: (c) 2013 by Ori Livneh <ori@wikimedia.org>
  :license: GNU General Public Licence 2.0 or later

"""
import errno
import fileinput
import inspect
import logging
import os
import re
import sys
import threading
import time

import zmq


logging.basicConfig(format='[ZMQ] %(asctime)s %(message)s', level=logging.INFO)

defaults = {
    'value_type': 'uint',
    'format': '%d',
    'units': 'events',
    'slope': 'positive',
    'time_max': 20,
    'description': 'messages published',
    'groups': 'EventLogging',
}


def iter_files(dir):
    """Recursively walk a file hierarchy."""
    return (os.path.join(dir, f) for dir, _, fs in os.walk(dir) for f in fs)


def iter_pubs(config_dir):
    """Discover local EventLogging publishers."""
    for line in fileinput.input(iter_files(config_dir)):
        match = re.match('tcp://\*:(\d+)', line)
        if match:
            name = os.path.basename(fileinput.filename())
            yield name, match.expand('tcp://127.0.0.1:\g<1>')


def monitor_pubs(endpoints, counters):
    """
    Count events streaming on a set of EventLogging publishers.

    *endpoints* is a dict that maps human-readable endpoint names to
    endpoint URIs. The names are used as metric names in Ganglia and
    as the ZMQ_IDENTITY of the underlying socket.

    """
    ctx = zmq.Context.instance()
    poller = zmq.Poller()

    sockets = {}
    for name, uri in endpoints.iteritems():
        logging.info('Registering %s (%s).', name, uri)
        socket = ctx.socket(zmq.SUB)
        socket.hwm = 1000
        socket.linger = 1000
        socket.setsockopt(zmq.RCVBUF, 65536)
        socket.connect(uri)
        socket.setsockopt(zmq.SUBSCRIBE, '')
        poller.register(socket, zmq.POLLIN)
        sockets[socket] = name

    while 1:
        try:
            for socket, _ in poller.poll():
                socket.recv(zmq.NOBLOCK)
                counters[sockets[socket]] += 1
        except KeyboardInterrupt:
            # PyZMQ 13.0.x raises EINTR as KeyboardInterrupt.
            # Fixed in <https://github.com/zeromq/pyzmq/pull/338>.
            if any(f for f in inspect.trace() if 'check_rc' in f[3]):
                continue
            raise
        except zmq.ZMQError as e:
            # Calls interrupted by EINTR should be re-tried.
            if e.errno == errno.EINTR:
                continue
            raise


def metric_init(params):
    """
    Initialize metrics.

    Recurses through /etc/eventlogging.d in search of local EventLogging
    publishers, spawn a worker thread to monitor them, and return a list of
    metric descriptors.

    """
    prefix = params.get('prefix', 'eventlogging_')
    config_dir = params.get('config_dir', '/etc/eventlogging.d')
    pubs = {prefix + k: v for k, v in iter_pubs(config_dir)}
    counters = {k: 0 for k in pubs}

    thread = threading.Thread(target=monitor_pubs, args=(pubs, counters))
    thread.daemon = True
    thread.start()

    for _ in range(20):
        time.sleep(0.1)
        if sum(counters.values()):
            break

    return [dict(defaults, name=p, call_back=counters.get) for p in pubs]


def metric_cleanup():
    """
    Clean-up handler.

    Gmond requires that this function be defined.

    """
    pass


def self_test():
    """
    Perform self-test. Parses *argv* as metric parameters.
    Message counts are polled and outputted every five seconds.

    """
    params = dict(arg.split('=') for arg in sys.argv[1:])
    descriptors = metric_init(params)

    while 1:
        for descriptor in descriptors:
            name = descriptor['name']
            call_back = descriptor['call_back']
            logging.info('%s: %s', name, call_back(name))
        time.sleep(5)


if __name__ == '__main__':
    self_test()
