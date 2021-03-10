# -*- coding: utf-8 -*-
"""
  eventlogging.parse
  ~~~~~~~~~~~~~~~~~~

  This module provides a scanf-like parser for raw log lines.

  The format specifiers hew closely to those accepted by varnishncsa.
  See the `varnishncsa documentation <https://www.varnish-cache.org
  /docs/trunk/reference/varnishncsa.html>`_ for details.

  Field specifiers
  ================

  +--------+-----------------------------+
  | Symbol | Field                       |
  +========+=============================+
  |   %h   | Client IP                   |
  +--------+-----------------------------+
  |   %j   | JSON event object           |
  +--------+-----------------------------+
  |   %q   | Query-string-encoded JSON   |
  +--------+-----------------------------+
  |   %t   | Timestamp in NCSA format    |
  +--------+-----------------------------+
  | %{..}i | Tab-delimited string        |
  +--------+-----------------------------+
  | %{..}s | Space-delimited string      |
  +--------+-----------------------------+
  | %{..}d | Integer                     |
  +--------+-----------------------------+

   '..' is the desired property name for the capturing group.

"""
from __future__ import division, unicode_literals

import calendar
import datetime
import re
import time
import uuid

from .compat import json, unquote_plus, uuid5
from .crypto import keyhasher, rotating_key


__all__ = ('LogParser', 'ncsa_to_unix', 'ncsa_utcnow', 'capsule_uuid')

# Format string (as would be passed to `strftime`) for timestamps in
# NCSA Common Log Format.
NCSA_FORMAT = '%Y-%m-%dT%H:%M:%S'

# Formats event capsule objects into URLs using the combination of
# origin hostname, sequence ID, and timestamp. This combination is
# guaranteed to be unique. Example::
#
#   event://vanadium.eqiad.wmnet/?seqId=438763&timestamp=1359702955
#
EVENTLOGGING_URL_FORMAT = (
    'event://%(recvFrom)s/?seqId=%(seqId)s&timestamp=%(timestamp).10s')

# Specifies the length of time in seconds from the moment a key is
# generated until it is expired and replaced with a new key. The key is
# used to anonymize IP addresses.
KEY_LIFESPAN = datetime.timedelta(days=90)


def capsule_uuid(capsule):
    """Generate a UUID for a capsule object.

    Gets a unique URI for the capsule using `EVENTLOGGING_URL_FORMAT`
    and uses it to generate a UUID5 in the URL namespace.

    ..seealso:: `RFC 4122 <https://www.ietf.org/rfc/rfc4122.txt>`_.

    :param capsule: A capsule object (or any dictionary that defines
      `recvFrom`, `seqId`, and `timestamp`).

    """
    id = uuid5(uuid.NAMESPACE_URL, EVENTLOGGING_URL_FORMAT % capsule)
    return '%032x' % id.int


def ncsa_to_unix(ncsa_ts):
    """Converts an NCSA Common Log Format timestamp to an integer
    timestamp representing the number of seconds since UNIX epoch UTC.

    :param ncsa_ts: Timestamp in NCSA format.
    """
    return calendar.timegm(time.strptime(ncsa_ts, NCSA_FORMAT))


def ncsa_utcnow():
    """Gets the current UTC date and time in NCSA Common Log Format"""
    return time.strftime(NCSA_FORMAT, time.gmtime())


def decode_qson(qson):
    """Decodes a QSON (query-string-encoded JSON) object.
    :param qs: Query string.
    """
    return json.loads(unquote_plus(qson.strip('?;')))


# A crytographic hash function for hashing client IPs. Produces HMAC SHA1
# hashes by using the client IP as the message and a 64-byte byte string as
# the key. The key is generated at runtime and is refreshed every 90 days.
# It is not written anywhere. The hash value is useful for detecting spam
# (large volume of events sharing a common origin).
hash_ip = keyhasher(rotating_key(size=64, period=KEY_LIFESPAN.total_seconds()))


class LogParser(object):
    """Parses raw varnish/MediaWiki log lines into encapsulated events."""

    def __init__(self, format, ip_hasher=hash_ip):
        """Constructor.

        :param format: Format string.
        :param ip_hasher: function ip_hasher(ip) -> hashed ip.
        """
        self.format = format

        # A mapping of format specifiers to a tuple of (regexp, caster).
        self.format_specifiers = {
            'd': (r'(?P<%s>\d+)', int),
            'h': (r'(?P<clientIp>\S+)', ip_hasher),
            'i': (r'(?P<%s>[^\t]+)', str),
            'j': (r'(?P<capsule>\S+)', json.loads),
            'q': (r'(?P<capsule>\?\S+)', decode_qson),
            's': (r'(?P<%s>\S+)', str),
            't': (r'(?P<timestamp>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})',
                  ncsa_to_unix),
        }

        # Field casters, ordered by the relevant field's position in
        # format string.
        self.casters = []

        # Compiled regexp.
        format = re.sub(' ', r'\s+', format)
        raw = re.sub(r'(?<!%)%({(\w+)})?([dhijqst])', self._repl, format)
        self.re = re.compile(raw)

    def _repl(self, spec):
        """Replace a format specifier with its expanded regexp matcher
        and append its caster to the list. Called by :func:`re.sub`.
        """
        _, name, specifier = spec.groups()
        matcher, caster = self.format_specifiers[specifier]
        if name:
            matcher = matcher % name
        self.casters.append(caster)
        return matcher

    def parse(self, line):
        """Parse a log line into a map of field names / values."""
        match = self.re.match(line)
        if match is None:
            raise ValueError(self.re, line)
        keys = sorted(match.groupdict(), key=match.start)
        event = {k: f(match.group(k)) for f, k in zip(self.casters, keys)}
        event.update(event.pop('capsule'))
        event['uuid'] = capsule_uuid(event)
        return event

    def __repr__(self):
        return '<LogParser(\'%s\')>' % self.format
