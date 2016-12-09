# -*- coding: utf-8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.streams`.

"""
from __future__ import unicode_literals

import multiprocessing
import time
import unittest

import eventlogging
import zmq

from .fixtures import TimeoutTestMixin


def publish(pipe, interface='tcp://127.0.0.1'):
    """Listen on a :class:`multiprocessing.Pipe` and publish incoming
    messages over a :obj:`zmq.PUB` socket. Terminate upon receiving
    empty string."""
    context = zmq.Context()
    pub = context.socket(zmq.PUB)
    pub.setsockopt(zmq.LINGER, 0)
    port = pub.bind_to_random_port(interface)

    # Let the other party know what endpoint we are bound to.
    pipe.send('%s:%s' % (interface, port))

    for message in iter(pipe.recv, ''):
        time.sleep(0.05)
        pub.send_unicode(message)

    pub.close()


class ZmqTestCase(TimeoutTestMixin, unittest.TestCase):
    """Test case for ZeroMQ-related functionality."""

    def setUp(self):
        """Spin up a worker subprocess that will publish anything we
        pipe into it."""
        self.pipe, other_pipe = multiprocessing.Pipe()
        publisher = multiprocessing.Process(target=publish, args=[other_pipe])
        publisher.daemon = True
        publisher.start()
        self.addCleanup(publisher.terminate)
        self.endpoint = self.pipe.recv()
        super(ZmqTestCase, self).setUp()

    def tearDown(self):
        """Send kill sentinel to worker subprocess."""
        self.pipe.send('')
        super(ZmqTestCase, self).tearDown()

    def test_iter_sub_socket(self):
        """``iter_unicode`` receives string objects."""
        subscriber = eventlogging.streams.sub_socket(self.endpoint)
        subscriber = eventlogging.streams.iter_unicode(subscriber)
        self.pipe.send('Hello.')
        self.assertEqual(next(subscriber), 'Hello.')

    def test_iter_json(self):
        """``iter_json`` decodes JSON messages."""
        subscriber = eventlogging.streams.sub_socket(
            self.endpoint, identity='%s' % self.id())
        subscriber = eventlogging.streams.iter_json(subscriber)
        self.pipe.send('{"message":"secret"}')
        self.assertEqual(next(subscriber), dict(message='secret'))
