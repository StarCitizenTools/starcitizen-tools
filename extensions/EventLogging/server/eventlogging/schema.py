# -*- coding: utf-8 -*-
"""
  eventlogging.schema
  ~~~~~~~~~~~~~~~~~~~

  This module implements schema retrieval and validation. Schemas are
  referenced via SCIDs, which are tuples of (Schema name, Revision ID).
  Schemas are retrieved via HTTP and then cached in-memory. Validation
  uses :module:`jsonschema`.

"""
from __future__ import unicode_literals

import re

import jsonschema

import socket
import time

from .compat import integer_types, json, http_get, string_types

import uuid

__all__ = (
    'CAPSULE_SCID', 'create_event_error', 'get_schema',
    'SCHEMA_URL_FORMAT', 'validate'
)


# Regular expression which matches valid schema names.
SCHEMA_RE_PATTERN = r'[a-zA-Z0-9_-]{1,63}'
SCHEMA_RE = re.compile(r'^{0}$'.format(SCHEMA_RE_PATTERN))

# These REs will be used when constructing an ErrorEvent
# to extract the schema and revision out of a raw event
# string in the case it cannot be parsed as JSON.
RAW_SCHEMA_RE = re.compile(
    r'%22schema%22%3A%22({0})%22'.format(SCHEMA_RE_PATTERN)
)
RAW_REVISION_RE = re.compile(r'%22revision%22%3A(\d+)')

# URL of index.php on the schema wiki (same as
# '$wgEventLoggingSchemaApiUri').
SCHEMA_WIKI_API = 'https://meta.wikimedia.org/w/api.php'

# Template for schema article URLs. Interpolates SCIDs.
SCHEMA_URL_FORMAT = (
    SCHEMA_WIKI_API + '?action=jsonschema&title=%s&revid=%s&formatversion=2'
)

# Schemas retrieved via HTTP are cached in this dictionary.
schema_cache = {}

# SCID of the metadata object which wraps each event.
CAPSULE_SCID = ('EventCapsule', 10981547)

# TODO:
ERROR_SCID = ('EventError', 14035058)


def get_schema(scid, encapsulate=False):
    """Get schema from memory or HTTP."""
    schema = schema_cache.get(scid)
    if schema is None:
        schema = http_get_schema(scid)
        schema_cache[scid] = schema
    # We depart from the JSON Schema specifications by disallowing
    # additional properties by default.
    # See `<https://bugzilla.wikimedia.org/show_bug.cgi?id=44454>`_.
    schema.setdefault('additionalProperties', False)
    if encapsulate:
        capsule = get_schema(CAPSULE_SCID)
        capsule['properties']['event'] = schema
        return capsule
    return schema


def http_get_schema(scid):
    """Retrieve schema via HTTP."""
    validate_scid(scid)
    url = SCHEMA_URL_FORMAT % scid
    try:
        schema = json.loads(http_get(url))
    except (ValueError, EnvironmentError) as ex:
        raise jsonschema.SchemaError('Schema fetch failure: %s' % ex)
    jsonschema.Draft3Validator.check_schema(schema)
    return schema


def validate_scid(scid):
    """Validates an SCID.
    :raises :exc:`jsonschema.ValidationError`: If SCID is invalid.
    """
    schema, revision = scid
    if not isinstance(revision, integer_types) or revision < 1:
        raise jsonschema.ValidationError('Invalid revision ID: %s' % revision)
    if not isinstance(schema, string_types) or not SCHEMA_RE.match(schema):
        raise jsonschema.ValidationError('Invalid schema name: %s' % schema)


def validate(capsule):
    """Validates an encapsulated event.
    :raises :exc:`jsonschema.ValidationError`: If event is invalid.
    """
    try:
        scid = capsule['schema'], capsule['revision']
    except KeyError as ex:
        # If `schema` or `revision` keys are missing, a KeyError
        # exception will be raised. We re-raise it as a
        # :exc:`ValidationError` to provide a simpler API for callers.
        raise jsonschema.ValidationError('Missing key: %s' % ex)
    schema = get_schema(scid, encapsulate=True)
    jsonschema.Draft3Validator(schema).validate(capsule)


def create_event_error(
    raw_event,
    error_message,
    error_code,
    parsed_event=None
):
    """
    Creates an EventError around this raw_event string.
    If parsed_event is provided, The raw event's schema and revision
    will be included in the ErrorEvent as event.schema and event.revision.
    Otherwise these will be attempted to be extracted from the raw_event via
    a regex.  If this still fails, these will be set to 'unknown' and -1.
    """
    errored_schema = 'unknown'
    errored_revision = -1

    # If we've got a parsed event, then we can just get the schema
    # and revision out of the object.
    if parsed_event:
        errored_schema = parsed_event.get('schema', 'unknown')
        errored_revision = int(parsed_event.get('revision', -1))

    # otherwise attempt to get them out of the raw_event with a regex
    else:
        schema_match = RAW_SCHEMA_RE.search(raw_event)
        if schema_match:
            errored_schema = schema_match.group(1)

        revision_match = RAW_REVISION_RE.search(raw_event)
        if revision_match:
            errored_revision = int(revision_match.group(1))

    return {
        'schema': ERROR_SCID[0],
        'revision': ERROR_SCID[1],
        'wiki': '',
        'uuid': '%032x' % uuid.uuid1().int,
        'recvFrom': socket.getfqdn(),
        'timestamp': int(round(time.time())),
        'event': {
            'rawEvent': raw_event,
            'message': error_message,
            'code': error_code,
            'schema': errored_schema,
            'revision': errored_revision
        }
    }
