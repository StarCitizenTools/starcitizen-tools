# -*- coding: utf-8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains test fixtures.

"""
from __future__ import unicode_literals

import copy
import signal

import eventlogging
import eventlogging.factory
import sqlalchemy


TEST_SCHEMA_SCID = ('TestSchema', 123)

_schemas = {
    eventlogging.schema.CAPSULE_SCID: {
        'properties': {
            'clientIp': {
                'type': 'string'
            },
            'event': {
                'type': 'object',
                'required': True
            },
            'wiki': {
                'type': 'string',
                'required': True
            },
            'webHost': {
                'type': 'string'
            },
            'revision': {
                'type': 'integer',
                'required': True
            },
            'schema': {
                'type': 'string',
                'required': True
            },
            'recvFrom': {
                'type': 'string',
                'required': True
            },
            'seqId': {
                'type': 'integer'
            },
            'timestamp': {
                'type': 'number',
                'required': True,
                'format': 'utc-millisec'
            },
            'uuid': {
                'type': 'string',
                'required': True,
                'format': 'uuid5-hex'
            }
        },
        'additionalProperties': False
    },
    eventlogging.schema.ERROR_SCID: {
        'properties': {
            'rawEvent': {
                'type': 'string',
                'required': True
            },
            'message': {
                'type': 'string',
                'required': True
            },
            'code': {
                'type': 'string',
                'required': True,
                'enum': [
                    'processor',
                    'consumer',
                    'validation'
                ],
            },
            'schema': {
                'type': 'string',
                'required': True
            },
            'revision': {
                'type': 'integer',
                'required': True
            }
        }
    },
    TEST_SCHEMA_SCID: {
        'properties': {
            'value': {
                'type': 'string',
                'required': True
            },
            'nested': {
                'type': 'object',
                'properties': {
                    'deeplyNested': {
                        'type': 'object',
                        'properties': {
                            'pi': {
                                'type': 'number',
                            }
                        }
                    }
                }
            }
        }
    }
}


_event = {
    'event': {
        'value': '☆ 彡',
        'nested': {
            'deeplyNested': {
                'pi': 3.14159
            }
        }
    },
    'seqId': 12345,
    'clientIp': '127.0.0.1',
    'timestamp': 1358791834912,
    'wiki': 'enwiki',
    'webHost': 'en.m.wikipedia.org',
    'recvFrom': 'fenari',
    'revision': 123,
    'schema': 'TestSchema',
    'uuid': 'babb66f34a0a5de3be0c6513088be33e'
}

# {} is preferred and PHP side of EL
# should be translating empty events to {} but this is
# to test that [] also works
_incorrectly_serialized_empty_event = {
    'event': [],
    'seqId': 12345,
    'clientIp': '127.0.0.1',
    'timestamp': 1358791834912,
    'wiki': 'enwiki',
    'webHost': 'en.m.wikipedia.org',
    'recvFrom': 'fenari',
    'revision': 123,
    'schema': 'TestSchema',
    'uuid': 'babb66f34a0a5de3be0c6513088be33e'
}


class HttpRequestAttempted(RuntimeError):
    """Raised on attempt to retrieve a schema via HTTP."""
    pass


# We'll be replacing :func:`eventlogging.schemas.http_get_schema` with a
# mock object, so set aside an unpatched copy so we can clean up.
orig_http_get_schema = eventlogging.schema.http_get_schema


def mock_http_get_schema(scid):
    """Mock of :func:`eventlogging.schemas.http_get_schema`
    Used to detect when :func:`eventlogging.schemas.get_schema`
    delegates to HTTP retrieval.
    """
    raise HttpRequestAttempted('Attempted HTTP fetch: %s' % (scid,))


def _get_event():
    """ Creates events on demand with unique ids"""
    for i in range(1, 100):
        event = copy.deepcopy(_event)
        event['uuid'] = i
        yield event


class SchemaTestMixin(object):
    """A :class:`unittest.TestCase` mix-in for test cases that depend on
    schema look-ups."""

    def setUp(self):
        """Stub `http_get_schema` and pre-fill schema cache."""
        super(SchemaTestMixin, self).setUp()
        self.event = copy.deepcopy(_event)
        self.incorrectly_serialized_empty_event = copy.deepcopy(
            _incorrectly_serialized_empty_event)
        eventlogging.schema.schema_cache = copy.deepcopy(_schemas)
        eventlogging.schema.http_get_schema = mock_http_get_schema
        self.event_generator = _get_event()

    def tearDown(self):
        """Clear schema cache and restore stubbed `http_get_schema`."""
        super(SchemaTestMixin, self).tearDown()
        eventlogging.schema.schema_cache.clear()
        eventlogging.schema.http_get_schema = orig_http_get_schema

    def assertIsValid(self, event, msg=None):
        """Assert that capsule 'event' object validates."""
        return self.assertIsNone(eventlogging.validate(event), msg)

    def assertIsInvalid(self, event, msg=None):
        """Assert that capsule 'event' object fails validation."""
        with self.assertRaises(eventlogging.ValidationError, msg):
            eventlogging.validate(event)


class DatabaseTestMixin(SchemaTestMixin):
    """A :class:`unittest.TestCase` mix-in for database testing using an
    in-memory sqlite database."""

    def setUp(self):
        """Configure :class:`sqlalchemy.engine.Engine` and
        :class:`sqlalchemy.schema.MetaData` objects."""
        super(DatabaseTestMixin, self).setUp()
        self.engine = sqlalchemy.create_engine('sqlite://', echo=False)
        self.meta = sqlalchemy.MetaData(bind=self.engine)

    def tearDown(self):
        """Dispose of the database access objects."""
        super(DatabaseTestMixin, self).tearDown()
        self.meta.drop_all()
        self.engine.dispose()


class HttpSchemaTestMixin(object):
    """A :class:`unittest.TestCase` mix-in for stubbing HTTP responses."""

    http_resp = ''

    def setUp(self):
        """Replace `http_get` with stub."""
        super(HttpSchemaTestMixin, self).setUp()
        self.orig_http_get = eventlogging.schema.http_get
        eventlogging.schema.http_get = self.http_get_stub
        eventlogging.schema.schema_cache.clear()

    def tearDown(self):
        """Restore original `http_get`."""
        eventlogging.schema.http_get = self.orig_http_get

    def http_get_stub(self, url):
        """Test stub for `http_get`."""
        return self.http_resp


class HandlerTestMixin(object):
    def setUp(self):
        self.orig_writers = eventlogging.factory._writers.copy()
        eventlogging.factory._writers.clear()
        self.orig_readers = eventlogging.factory._readers.copy()
        eventlogging.factory._readers.clear()


class TimeoutTestMixin(object):
    """A :class:`unittest.TestCase` mix-in that imposes a time-limit on
    tests. Tests exceeding the limit are failed."""

    # Max time (in seconds) to allow tests to run before failing.
    max_time = 2

    def setUp(self):
        """Set the alarm."""
        super(TimeoutTestMixin, self).setUp()
        signal.signal(signal.SIGALRM, self.timeOut)
        signal.alarm(self.max_time)

    def tearDown(self):
        """Disable the alarm."""
        signal.alarm(0)

    def timeOut(self, signum, frame):
        """SIGALRM handler. Fails test if triggered."""
        self.fail('Timed out.')
