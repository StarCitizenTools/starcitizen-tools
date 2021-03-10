# -*- coding: utf-8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.compat`.

"""
from __future__ import unicode_literals

import multiprocessing
import os
import sys
import time
import unittest
import wsgiref.simple_server

import eventlogging


CI = 'TRAVIS' in os.environ or 'JENKINS_URL' in os.environ


class SingleServingHttpd(multiprocessing.Process):
    def __init__(self, resp):
        self.resp = resp.encode('utf-8')
        self.is_started = multiprocessing.Event()
        super(SingleServingHttpd, self).__init__()

    def run(self):
        def app(environ, start_response):
            start_response(str('200 OK'), [])
            return [self.resp]
        httpd = wsgiref.simple_server.make_server('127.0.0.1', 44080, app)
        self.is_started.set()
        stderr, sys.stderr = sys.stderr, open(os.devnull, 'w')
        try:
            httpd.handle_request()
        finally:
            sys.stderr, stderr = stderr, sys.stderr
            stderr.close()
        httpd.socket.close()


class UriSplitTestCase(unittest.TestCase):
    """Test cases for ``urisplit``."""

    def test_urisplit(self):
        uri = 'tcp://127.0.0.1:8600/?q=1#f=2'
        parts = eventlogging.urisplit(uri)
        self.assertEqual(parts.query, 'q=1')
        self.assertEqual(parts.fragment, 'f=2')


class HttpGetTestCase(unittest.TestCase):
    """Test cases for ``http_get``."""

    @unittest.skipIf(CI, 'Running in a CI environment')
    def test_http_get(self):
        """``http_get`` can pull content via HTTP."""
        server = SingleServingHttpd('secret')
        server.start()
        if not server.is_started.wait(2):
            self.fail('Server did not start within 2 seconds')
        response = eventlogging.http_get('http://127.0.0.1:44080')
        self.assertEqual(response, 'secret')


class MonotonicClockTestCase(unittest.TestCase):
    """Test cases for ``monotonic_clock``."""

    @unittest.skipIf(eventlogging.monotonic_clock == time.time,
                     'using non-monotonic time.time() as fallback')
    def test_monotonic_clock(self):
        """``monotonic_clock`` is indeed monotonic."""
        t1 = eventlogging.monotonic_clock()
        t2 = eventlogging.monotonic_clock()
        self.assertGreaterEqual(t2, t1)
