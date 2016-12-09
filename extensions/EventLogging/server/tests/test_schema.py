# -*- coding: utf-8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.schema`.

  This does not test JSON Schema validation per se, since validation
  is delegated to  :module:`jsonschema`, which comes with its own
  comprehensive suite of unit tests. This module specifically tests
  schema cache / HTTP retrieval and the handling of complex
  ('encapsulated') event objects.

"""
from __future__ import unicode_literals

import unittest

import eventlogging

from .fixtures import (
    HttpRequestAttempted,
    HttpSchemaTestMixin,
    SchemaTestMixin,
    TEST_SCHEMA_SCID
)


class HttpSchemaTestCase(HttpSchemaTestMixin, unittest.TestCase):
    """Tests for :func:`eventlogging.schema.http_get_schema`."""

    def test_valid_resp(self):
        """Test handling of HTTP response containing valid schema."""
        self.http_resp = '{"properties":{"value":{"type":"number"}}}'
        schema = eventlogging.schema.http_get_schema(TEST_SCHEMA_SCID)
        self.assertEqual(schema, {'properties': {'value': {'type': 'number'}}})

    def test_invalid_resp(self):
        """Test handling of HTTP response not containing valid schema."""
        self.http_resp = '"foo"'
        with self.assertRaises(eventlogging.SchemaError):
            eventlogging.schema.http_get_schema(TEST_SCHEMA_SCID)

    def test_caching(self):
        """Valid HTTP responses containing JSON Schema are cached."""
        self.http_resp = '{"properties":{"value":{"type":"number"}}}'
        eventlogging.get_schema(TEST_SCHEMA_SCID)
        self.assertIn(TEST_SCHEMA_SCID, eventlogging.schema.schema_cache)


class ValidateScidTestCase(unittest.TestCase):
    """Tests for :func:`eventlogging.schema.validate_scid`."""

    schema_name, revision_id = TEST_SCHEMA_SCID

    def test_valid_scid(self):
        """Valid SCIDs validate."""
        scid = self.schema_name, self.revision_id
        self.assertIsNone(eventlogging.schema.validate_scid(scid))

    def test_invalid_schema_name(self):
        """Invalid schema name triggers SCID validation failure."""
        for invalid_schema_name in ('Foo%', 'X' * 64, 123):
            scid = invalid_schema_name, self.revision_id
            with self.assertRaises(eventlogging.ValidationError):
                eventlogging.schema.validate_scid(scid)

    def test_invalid_revision_id(self):
        """Invalid revision ID triggers SCID validation failure."""
        for invalid_revision_id in (-1, 0, '1'):
            scid = self.schema_name, invalid_revision_id
            with self.assertRaises(eventlogging.ValidationError):
                eventlogging.schema.validate_scid(scid)


class SchemaTestCase(SchemaTestMixin, unittest.TestCase):
    """Tests for :module:`eventlogging.schema`."""

    def test_valid_event(self):
        """Valid events validate."""
        self.assertIsValid(self.event)

    def test_incomplete_scid(self):
        """Missing SCID in capsule object triggers validation failure."""
        self.event.pop('schema')
        self.assertIsInvalid(self.event)

    def test_missing_property(self):
        """Missing property in capsule object triggers validation failure."""
        self.event.pop('timestamp')
        self.assertIsInvalid(self.event)

    def test_missing_nested_property(self):
        """Missing property in nested event triggers validation failure."""
        self.event['event'].pop('value')
        self.assertIsInvalid(self.event)

    def test_extra_property(self):
        """Missing property in nested event triggers validation failure."""
        self.event['event']['season'] = 'summer'
        self.assertIsInvalid(self.event)

    def test_schema_retrieval(self):
        """Schemas missing from the cache are retrieved via HTTP."""
        # Pop the schema from the cache.
        eventlogging.schema.schema_cache.pop(TEST_SCHEMA_SCID)
        with self.assertRaises(HttpRequestAttempted) as context:
            eventlogging.validate(self.event)
            self.assertEqual(context.exception.rev_id, 123)

    def test_encapsulated_schema(self):
        """get_schema() returns encapsulated schema if requested."""
        encapsulated = eventlogging.get_schema(eventlogging.CAPSULE_SCID)
        encapsulated['event'] = eventlogging.get_schema(TEST_SCHEMA_SCID)
        self.assertEqual(eventlogging.get_schema(TEST_SCHEMA_SCID, True),
                         encapsulated)

    def test_capsule_uuid(self):
        """capsule_uuid() generates a unique UUID for capsule objects."""
        self.assertEqual(eventlogging.capsule_uuid(self.event),
                         'babb66f34a0a5de3be0c6513088be33e')

    def test_empty_event(self):
        """An empty event with no mandatory properties should validate"""
        self.assertIsValid(self.incorrectly_serialized_empty_event)

    def test_create_event_error_unparsed(self):
        """
        create_event_error() should create a valid
        EventError object without schema or revision set.
        """

        invalid_raw_event = "Duh this won't validate against any schema."
        error_message = "This is just a test."
        error_code = "processor"
        event_error = eventlogging.create_event_error(
            invalid_raw_event,
            error_message,
            error_code
        )
        # Test that this event validates against the EventError schema.
        self.assertIsValid(event_error)
        self.assertEqual(
            event_error['schema'],
            eventlogging.schema.ERROR_SCID[0]
        )
        self.assertEqual(
            event_error['revision'],
            eventlogging.schema.ERROR_SCID[1]
        )
        self.assertEqual(
            event_error['event']['rawEvent'],
            invalid_raw_event
        )
        self.assertEqual(
            event_error['event']['message'],
            error_message
        )
        # assert that schema and revision are the defaults, since there's
        # there is no parsed_event and these are not even present in this
        # raw_event.
        self.assertEqual(
            event_error['event']['schema'],
            'unknown'
        )
        self.assertEqual(
            event_error['event']['revision'],
            -1
        )

    def test_create_event_error_parsed(self):
        """
        create_event_error() should create a valid
        EventError object with schema and revision set.
        """

        invalid_raw_event = "Duh this won't validate against any schema."
        error_message = "This is just a test."
        error_code = "processor"
        parsed_event = {
            'schema': 'Nonya',
            'revision': 12345
        }

        event_error = eventlogging.create_event_error(
            invalid_raw_event,
            error_message,
            error_code,
            parsed_event
        )
        # Test that this event validates against the EventError schema.
        self.assertIsValid(event_error)
        self.assertEqual(
            event_error['schema'],
            eventlogging.schema.ERROR_SCID[0]
        )
        self.assertEqual(
            event_error['revision'],
            eventlogging.schema.ERROR_SCID[1]
        )
        self.assertEqual(
            event_error['event']['rawEvent'],
            invalid_raw_event
        )
        self.assertEqual(
            event_error['event']['message'],
            error_message
        )
        # assert that schema and revision the same as in parsed_event
        self.assertEqual(
            event_error['event']['schema'],
            'Nonya'
        )
        self.assertEqual(
            event_error['event']['revision'],
            12345
        )

    def test_create_event_error_raw_schema_and_revision(self):
        """
        create_event_error() should create a valid
        EventError object with schema and revision set, extracted
        via a regex out of the raw_event.
        """

        invalid_raw_event = '?%7B%22event%22%3A%7B%22mobileMode%22%3A' \
            '%22stable%22%2C%22name%22%3A%22home%22%2C%22destination%22%3A' \
            '%22%2Fwiki%2FPagina_principale%22%7D%2C%22revision%22%3A' \
            '11568715%2C%22schema%22%3A' \
            '%22MobileWebMainMenuClickTracking%22%2C' \
            '%22webHost%22%3A%12345terfdit.m.wikipedia.org%22%2C%22wiki%22' \
            '%3A%22itwiki%22%7D;	cp3013.esams.wmnet	4724275	' \
            '2015-09-21T21:55:27	1.2.3.4	"Mozilla"'

        print(invalid_raw_event)
        error_message = "This is just a test."
        error_code = "processor"

        event_error = eventlogging.create_event_error(
            invalid_raw_event,
            error_message,
            error_code,
        )
        # Test that this event validates against the EventError schema.
        self.assertIsValid(event_error)
        self.assertEqual(
            event_error['schema'],
            eventlogging.schema.ERROR_SCID[0]
        )
        self.assertEqual(
            event_error['revision'],
            eventlogging.schema.ERROR_SCID[1]
        )
        self.assertEqual(
            event_error['event']['rawEvent'],
            invalid_raw_event
        )
        self.assertEqual(
            event_error['event']['message'],
            error_message
        )
        # assert that schema and revision the same as in the invalid raw event
        self.assertEqual(
            event_error['event']['schema'],
            'MobileWebMainMenuClickTracking'
        )
        self.assertEqual(
            event_error['event']['revision'],
            11568715
        )
