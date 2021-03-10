# -*- coding: utf-8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.crypto`.

"""
from __future__ import unicode_literals

import logging
import os
import subprocess
import shutil
import tempfile
import time
import unittest

import eventlogging


class KeyHasherTestCase(unittest.TestCase):
    """Test case for :func:`eventlogging.keyhasher`."""

    def test_hash_function(self):
        """``keyhasher`` produces HMAC SHA1 using key iterator for keys"""
        hash_func = eventlogging.keyhasher((b'key1', b'key2'))
        self.assertEqual(
            hash_func('message1'),
            'e45a01bfebb0d5596564cc7b712b2d570041a839'
        )
        self.assertEqual(
            hash_func('message2'),
            'c8ec32b32d5bd7dc5a6a0b203f7f220bb641f52c'
        )

    def test_keys_depleted(self):
        """``keyhasher`` raises StopIteration if key iterator is depleted."""
        hash_func = eventlogging.keyhasher(())
        with self.assertRaises(StopIteration):
            hash_func('message')


class RotatingKeyTestCase(unittest.TestCase):
    """Test case for :func:`eventlogging.rotating_key`."""

    def test_key_repeats(self):
        """``rotating_key`` yields the same key until that key expires."""
        key_iter = eventlogging.rotating_key(size=64, period=60)
        self.assertEqual(next(key_iter), next(key_iter))

    def test_key_expires(self):
        """``rotating_key`` produces a new key when the old key expires."""
        key_iter = eventlogging.rotating_key(size=64, period=0.001)
        key1 = next(key_iter)
        time.sleep(0.01)
        key2 = next(key_iter)
        self.assertNotEqual(key1, key2)


class SharedRotatingTokenTestCase(unittest.TestCase):
    """Test case for :class:`eventlogging.SharedRotatingToken`."""

    @classmethod
    def _get_etcd_exe(cls):
        """
        Finds the etcd executlabe in PATH.
        """
        PROGRAM = 'etcd'
        program_path = None
        for path in os.environ["PATH"].split(os.pathsep):
            path = path.strip('"')
            exe_file = os.path.join(path, PROGRAM)
            if os.path.isfile(exe_file) and os.access(exe_file, os.X_OK):
                program_path = exe_file
                break
        if not program_path:
            raise Exception(
                'etcd not in path!  Install etcd server package '
                'to run these tests.'
            )
        return program_path

    @classmethod
    def setUpClass(cls):
        """
        Start a temporary test instance of etcd in order to
        test SharedRotatingToken.
        """
        program = cls._get_etcd_exe()
        cls.directory = tempfile.mkdtemp(prefix='eventlogging-test')
        cls.etcd_port = 7239
        cls.processHelper = EtcdProcessHelper(
            cls.directory,
            proc_name=program, port=cls.etcd_port)
        cls.processHelper.run()

    @classmethod
    def tearDownClass(cls):
        cls.processHelper.stop()
        shutil.rmtree(cls.directory)

    def test_token_repeats(self):
        rotatingToken = eventlogging.SharedRotatingToken(
            'test_token_repeats', lifetime=300, size=4, port=self.etcd_port
        )
        self.assertEqual(rotatingToken.token, rotatingToken.token)

    def test_token_expires(self):
        rotatingToken = eventlogging.SharedRotatingToken(
            'test_token_expires', lifetime=1, size=4, port=self.etcd_port
        )
        token1 = rotatingToken.token
        time.sleep(2)
        token2 = rotatingToken.token
        self.assertNotEqual(token1, token2)

    def test_token_iterator(self):
        rotatingToken = eventlogging.SharedRotatingToken(
            'test_token_iterator', lifetime=1, size=4, port=self.etcd_port
        )

        tokenA = next(rotatingToken)
        self.assertEqual(tokenA, next(rotatingToken))
        time.sleep(2)
        self.assertNotEqual(tokenA, next(rotatingToken))


# copy/pasted and modified from conftool integration tests
# https://github.com/wikimedia/operations-software-conftool/blob/master/conftool/tests/integration/__init__.py

class EtcdProcessHelper(object):
    def __init__(
        self,
        base_directory,
        proc_name='etcd',
        port=2379,
        internal_port=2380,
        cluster=False,
        tls=False
    ):
        self.log = logging.getLogger(__name__ + '.' + self.__class__.__name__)
        self.base_directory = base_directory
        self.proc_name = proc_name
        self.port = port
        self.internal_port = internal_port
        self.proc = None
        self.cluster = cluster
        self.schema = 'http://'
        if tls:
            self.schema = 'https://'

    def run(self, proc_args=None):
        if self.proc is not None:
            raise Exception("etcd already running with pid %d", self.proc.pid)
        client = '%s127.0.0.1:%d' % (self.schema, self.port)
        daemon_args = [
            self.proc_name,
            '-data-dir', self.base_directory,
            '-name', 'test-node',
            '-advertise-client-urls', client,
            '-listen-client-urls', client
        ]
        if proc_args:
            daemon_args.extend(proc_args)

        # Quiet down etcd process stderr output!
        DEVNULL = open(os.devnull, 'wb')
        daemon = subprocess.Popen(daemon_args, stderr=DEVNULL)
        self.log.debug('Started etcd with pid %d' % daemon.pid)
        self.log.debug('etcd params: %s' % daemon_args)
        time.sleep(2)
        self.proc = daemon

    def stop(self):
        self.proc.kill()
        self.proc = None
