# -*- coding: utf-8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.jrm`.

"""
from __future__ import unicode_literals

import datetime
import itertools
import unittest

import eventlogging
import sqlalchemy
import sqlalchemy.sql

from .fixtures import (DatabaseTestMixin, TEST_SCHEMA_SCID)


class JrmTestCase(DatabaseTestMixin, unittest.TestCase):
    """Test case for :module:`eventlogging.jrm`."""

    def test_lazy_table_creation(self):
        """If an attempt is made to store an event for which no table
        exists, the schema is automatically retrieved and a suitable
        table generated."""
        events_batch = [(TEST_SCHEMA_SCID, [self.event])]
        eventlogging.store_sql_events(self.meta, events_batch)
        self.assertIn('TestSchema_123', self.meta.tables)
        table = self.meta.tables['TestSchema_123']
        # is the table on the db  and does it have the right data?
        s = sqlalchemy.sql.select([table])
        results = self.engine.execute(s)
        row = results.fetchone()
        # see columns with print table.c
        self.assertEqual(row['clientIp'], self.event['clientIp'])

    def test_column_names(self):
        """Generated tables contain columns for each relevant field."""
        t = eventlogging.jrm.declare_table(self.meta, TEST_SCHEMA_SCID)

        # The columns we expect to see are..
        cols = set(eventlogging.utils.flatten(self.event))  # all properties
        cols -= set(eventlogging.jrm.NO_DB_PROPERTIES)      # unless excluded
        cols.add('uuid')                                    # plus uuid

        self.assertSetEqual(set(t.columns.keys()), cols)

    def test_index_creation(self):
        """The ``timestamp`` column is indexed by default."""
        t = eventlogging.jrm.declare_table(self.meta, TEST_SCHEMA_SCID)
        cols = {column.name for index in t.indexes for column in index.columns}
        self.assertIn('timestamp', cols)

    def test_flatten(self):
        """``flatten`` correctly collapses deeply nested maps."""
        flat = eventlogging.utils.flatten(self.event)
        self.assertEqual(flat['event_nested_deeplyNested_pi'], 3.14159)

    def test_encoding(self):
        """Timestamps and unicode strings are correctly encoded."""
        events_batch = [(TEST_SCHEMA_SCID, [self.event])]
        eventlogging.jrm.store_sql_events(self.meta, events_batch)
        table = eventlogging.jrm.get_table(self.meta, TEST_SCHEMA_SCID)
        row = table.select().execute().fetchone()
        self.assertEqual(row['event_value'], '☆ 彡')
        self.assertEqual(row['uuid'], 'babb66f34a0a5de3be0c6513088be33e')
        self.assertEqual(
            row['timestamp'],
            datetime.datetime(2013, 1, 21, 18, 10, 34)
        )

    def test_reflection(self):
        """Tables which exist in the database but not in the MetaData cache are
        correctly reflected."""
        events_batch = [(TEST_SCHEMA_SCID, [self.event])]
        eventlogging.store_sql_events(self.meta, events_batch)

        # Tell Python to forget everything it knows about this database
        # by purging ``MetaData``. The actual data in the database is
        # not altered by this operation.
        del self.meta
        self.meta = sqlalchemy.MetaData(bind=self.engine)

        # Although ``TestSchema_123`` exists in the database, SQLAlchemy
        # is not yet aware of its existence:
        self.assertNotIn('TestSchema_123', self.meta.tables)

        # The ``checkfirst`` arg to :func:`sqlalchemy.Table.create`
        # will ensure that we don't attempt to CREATE TABLE on the
        # already-existing table:
        events_batch = [(TEST_SCHEMA_SCID, [self.event])]
        eventlogging.store_sql_events(self.meta, events_batch, True)
        self.assertIn('TestSchema_123', self.meta.tables)

    def test_happy_case_insert_more_than_one_event(self):
        """Insert more than one event on database using batch_write"""
        another_event = next(self.event_generator)
        events_batch = [(TEST_SCHEMA_SCID, [another_event, self.event])]
        eventlogging.store_sql_events(self.meta, events_batch)
        table = self.meta.tables['TestSchema_123']
        # is the table on the db  and does it have the right data?
        s = sqlalchemy.sql.select([table])
        results = self.engine.execute(s)
        # the number of records in table must be the list size
        rows = results.fetchall()
        self.assertEqual(len(rows), 2)

    def test_insertion_of_multiple_events_with_a_duplicate(self):
        """"If an insert with multiple events includes
        a duplicate and replace=True we have to
        insert the other items.
        """
        # insert event
        events_batch = [(TEST_SCHEMA_SCID, [self.event])]
        eventlogging.jrm.store_sql_events(self.meta, events_batch)
        # now try to insert list of events in which this event is included
        another_event = next(self.event_generator)
        event_list = [another_event, self.event]
        events_batch = [(TEST_SCHEMA_SCID, event_list)]
        eventlogging.store_sql_events(self.meta, events_batch, replace=True)

        # we should still have to insert the other record though
        table = self.meta.tables['TestSchema_123']
        s = sqlalchemy.sql.select([table])
        results = self.engine.execute(s)
        rows = results.fetchall()
        self.assertEqual(len(rows), 2)

    def test_event_queue_is_empty(self):
        """An empty event queue is handled well
        No exception is raised"""
        event_list = []
        eventlogging.store_sql_events(self.meta, event_list)

    def test_grouping_of_events_happy_case(self):
        """Events belonging to the same schema with the same
        set of fields are gropued together """
        another_event = next(self.event_generator)

        event_list = [another_event, self.event]

        queue = [eventlogging.flatten(event_list.pop())
                 for _ in range(len(event_list))]
        queue.sort(key=eventlogging.jrm.insert_sort_key)

        uniquekeys = []
        batches = itertools.groupby(queue, eventlogging.jrm.insert_sort_key)
        for k, events in batches:
            uniquekeys.append(k)
        # we should have stored one key as events can be batched together
        self.assertEqual(len(uniquekeys), 1)

    def test_insert_events_with_different_set_of_optional_fields(self):
        """Events belonging to the same schema with a different
        set of optional fields are inserted correctly"""
        another_event = next(self.event_generator)
        # ensure both events get inserted?
        events_batch = [(TEST_SCHEMA_SCID, [another_event, self.event])]
        eventlogging.store_sql_events(self.meta, events_batch)
        table = self.meta.tables['TestSchema_123']
        # is the table on the db  and does it have the right data?
        s = sqlalchemy.sql.select([table])
        results = self.engine.execute(s)
        # the number of records in table must be the list size
        rows = results.fetchall()
        self.assertEqual(len(rows), 2)
