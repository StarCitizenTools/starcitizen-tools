# -*- coding: utf-8 -*-
"""
  eventlogging.crypto
  ~~~~~~~~~~~~~~~~~~~

  This module implements ephemeral key-hashing, used by EventLogging to
  anonymize IP addresses.

  .. admonition :: The intent of the code in this module is to frustrate
                   casual and unthinking misuse of data by researchers.
                   The scrambling process is not resilient and it can be
                   inverted. What more, scrambled IP addresses can be
                   used to cross-reference events by origin, which can
                   facilitate identification by means of accumulation of
                   partially-identifying data. Upholding privacy and
                   anonymity in your data infrastructure will require
                   additional measures.

"""
from __future__ import unicode_literals

import binascii
import etcd
import hashlib
import hmac
import inspect
import os
import time
import logging
from .compat import items

__all__ = ('keyhasher', 'rotating_key', 'SharedRotatingToken')


def rotating_key(size, period):
    """Produce a random key of `size` bytes and yield it repeatedly until
    `period` seconds expire, at which point a new key is produced.

    :param size: Byte length of key.
    :param period: Key lifetime in seconds.
    """
    while 1:
        key = os.urandom(size)
        created = time.time()
        while (time.time() - created) <= period:
            yield key


class SharedRotatingToken(object):
    """
    Uses etcd to maintain an auto rotating shared token that
    resets every ttl.  This is useful when running multiple
    eventlogging processors, so that they may all hash client
    IP addresses using the same token.

    Usage:
        s = SharedRotatingToken(
            'ip_hash',
            size=8,
            lifetime=5
        )

        # Get the current token
        print(s.token)

        # Or use as an iterator
        for token in s:
            print(token)
            time.sleep(1)

    """
    def __init__(
        self,
        name,
        lifetime=7776000,  # 90 days
        size=64,           # 64 bytes
        **kwargs
    ):
        """
        :param name: Unique name of token.
        :param size: Byte length of token.  Default: 64
        :param lifetime: Token lifetime in seconds. Default: 90 days
        :param etcd_kwargs: args to pass to etcd.Client.
               If allow_reconnect is not given, this will default to True.
        """
        logging.debug("Using etcd for rotating shared token '%s' "
                      "with a ttl of %s seconds", name, lifetime)

        self.lifetime = lifetime
        self.size = size
        self.name = name

        # Use an etcd key that is uniquely identified by name and lifetime.
        # This defends against processes possibly specifying
        # the same token name but different lifetimes.  If that were to
        # happen, it would be possible for the key in etcd to be written
        # with different ttls at varying times, depending on which
        # process write the key after it expires.
        self.etcd_key = '/eventlogging/token/%s/%s' % (name, self.lifetime)

        # Parse remaining kwargs and pass them to etcd.Client
        # if they are args that etcd.Client takes.
        etcd_kwargs = {
            k: v for k, v in items(kwargs)
            if k in inspect.getargspec(etcd.Client.__init__).args
        }
        # Default to allow_reconnect=True
        etcd_kwargs['allow_reconnect'] = etcd_kwargs.get(
            'allow_reconnect', True
        )

        self.etcd_client = etcd.Client(**etcd_kwargs)

        self.expiry_timestamp = 0
        self._token = None

    def __iter__(self):
        return self

    def next(self):
        return self.token

    def get(self):
        """
        Attempts to read etcd key.  If the key doesn't exist,
        self._set() will be called to set a new token value in etcd.

        Returns the value of the key in etcd.
        """
        try:
            res = self.etcd_client.read(self.etcd_key)
        except etcd.EtcdKeyNotFound:
            res = self._set()

        # The future expiration timestamp is now + key's remaining ttl.
        # NOTE: It is possible for etcd to return a response
        # with ttl=None while this key is expiring.  I believe
        # this happens when ttl is 0?
        # Use 0 as the ttl if ttl is None.
        self.expiry_timestamp = time.time() + (res.ttl or 0)

        return res.value.encode('utf-8')

    @property
    def token(self):
        # If _token isn't initialized OR if the
        # expiration timestamp has passed,
        # then ask etcd for a new token
        if not self._token or time.time() > self.expiry_timestamp:
            logging.debug(
                "Cached token %s has expired after %s seconds "
                "(or is not initialized), attempting to get a new one.",
                self.name, self.lifetime
            )
            self._token = self.get()

        return self._token

    def _set(self):
        """
        Generates a new random token of self.size and attempts save it in etcd
        at self. etcd_key.  If the key already exists in etcd, then this new
        key will not be written.  Whatever entry is then in etcd will be
        returned.
        """
        try:
            self.etcd_client.write(
                self.etcd_key,
                # etcd doesn't seem to handle binary values well.
                # Encode this random token as a hex string.
                binascii.hexlify(os.urandom(self.size)),
                ttl=self.lifetime,
                prevExist=False
            )
        except etcd.EtcdAlreadyExist:
            pass
        # NOTE: It is possible, but very unlikely that between the
        # EtcdAlreadyExist exception AND this read, the key is
        # expired and removed from etcd.
        return self.etcd_client.read(self.etcd_key)


def keyhasher(keys, digestmod=hashlib.sha1):
    """Returns an HMAC function that acquires keys from an iterator."""
    keys_iter = iter(keys)

    def hash_func(msg):
        """HMAC function bound to a key iterator and hash function."""
        code = hmac.new(next(keys_iter), msg.encode('utf-8'), digestmod)
        return code.hexdigest()

    return hash_func
