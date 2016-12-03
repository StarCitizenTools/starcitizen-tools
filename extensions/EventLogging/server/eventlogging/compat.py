# -*- coding: utf-8 -*-
"""
  eventlogging.compat
  ~~~~~~~~~~~~~~~~~~~

  The source code for EventLogging aims to be compatible with both
  Python 2 and 3 without requiring any translation to run on one or the
  other version. This module supports this goal by providing import
  paths and helper functions that wrap differences between Python 2 and
  Python 3.

"""
# flake8: noqa
# pylint: disable=E0611, F0401, E1101
from __future__ import unicode_literals

import functools
import hashlib
import operator
import sys
import time
import uuid
import warnings

try:
    from .lib.monotonic import monotonic as monotonic_clock
except ImportError:
    warnings.warn('Using non-monotonic time.time() as last-resort fallback '
                  'for eventlogging.monotonic_clock()', RuntimeWarning)
    monotonic_clock = time.time

try:
    import simplejson as json
except ImportError:
    import json


__all__ = ('http_get', 'integer_types', 'items', 'json', 'monotonic_clock',
           'string_types', 'unquote_plus', 'urisplit', 'urlopen', 'urlencode',
           'uuid5')

PY3 = sys.version_info[0] == 3

if PY3:
    items = operator.methodcaller('items')
    from urllib.request import urlopen
    from urllib.parse import (unquote_to_bytes as unquote, urlsplit, urlencode,
                              parse_qsl, SplitResult)
    string_types = str,
    integer_types = int,
else:
    items = operator.methodcaller('iteritems')
    from urllib import unquote, urlencode
    from urllib2 import urlopen
    from urlparse import urlsplit, parse_qsl, SplitResult
    string_types = basestring,
    integer_types = int, long


def urisplit(uri):
    """Like `urlparse.urlsplit`, except always parses query and fragment
    components, regardless of URI scheme."""
    scheme, netloc, path, query, fragment = urlsplit(uri)
    if not fragment and '#' in path:
        path, fragment = path.split('#', 1)
    if not query and '?' in path:
        path, query = path.split('?', 1)
    return SplitResult(scheme, netloc, path, query, fragment)


def unquote_plus(unicode):
    """Replace %xx escapes by their single-character equivalent."""
    unicode = unicode.replace('+', ' ')
    bytes = unicode.encode('utf-8')
    return unquote(bytes).decode('utf-8')


def http_get(url):
    """Simple wrapper around the standard library's `urlopen` function which
    works around a circular ref. See <https://bugs.python.org/issue1208304>.
    """
    req = None
    try:
        req = urlopen(url)
        return req.read().decode('utf-8')
    finally:
        if req is not None:
            if hasattr(req, 'fp') and hasattr(req.fp, '_sock'):
                req.fp._sock.recv = None
            req.close()


@functools.wraps(uuid.uuid5)
def uuid5(namespace, name):
    """Generate UUID5 for `name` in `namespace`."""
    # Python 2 expects `name` to be bytes; Python 3, unicode. This
    # variant expects unicode strings in both Python 2 and Python 3.
    hash = hashlib.sha1(namespace.bytes + name.encode('utf-8')).digest()
    return uuid.UUID(bytes=hash[:16], version=5)
