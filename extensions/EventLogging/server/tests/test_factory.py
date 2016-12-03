# -*- coding: utf-8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.factory`.

"""
from __future__ import unicode_literals

import unittest

import eventlogging
import eventlogging.factory


def fail_at_third_yield(uri, **kwargs):
    yield "yield #1"
    yield "yield #2"
    raise RuntimeError("@yield #3")


class ListWriterStub:
    def __init__(self):
        self.is_writer_closed = False
        self.values = []

    def get_writer(self):
        def writer(uri, **kwargs):
            try:
                while 1:
                    self.values.append((yield))
            except GeneratorExit:
                self.is_writer_closed = True
        return writer


class FactoryTestCase(unittest.TestCase):
    """Test case for URI-based reader/writer factories."""

    def test_drive_closes_writer_upon_reader_exception(self):
        eventlogging.reads('fail-at-third-yield')(fail_at_third_yield)
        list_writer_stub = ListWriterStub()

        eventlogging.writes('echo-writer')(list_writer_stub.get_writer())

        try:
            eventlogging.drive('fail-at-third-yield://', 'echo-writer://')
            self.fail("No exception got thrown")
        except RuntimeError as e:
            self.assertEqual(list_writer_stub.values, ["yield #1", "yield #2"],
                             "Yielded elements do not match")
            self.assertEqual(str(e), "@yield #3",
                             "Unknown RuntimeError raised")
            self.assertTrue(list_writer_stub.is_writer_closed,
                            "Writer has not been closed")
        finally:
            eventlogging.factory._writers.pop('echo-writer')
            eventlogging.factory._readers.pop('fail-at-third-yield')

    def test_cast_string(self):
        """``cast_string`` casts builtin looking strings to builtin types."""
        self.assertEqual(
            "string",
            eventlogging.factory.cast_string("string")
        )
        self.assertEqual(
            u"string",
            eventlogging.factory.cast_string(u"string")
        )
        self.assertEqual(
            1.1,
            eventlogging.factory.cast_string("1.1")
        )
        self.assertEqual(
            10,
            eventlogging.factory.cast_string("10")
        )
        self.assertEqual(
            False,
            eventlogging.factory.cast_string("False")
        )
        self.assertEqual(
            True,
            eventlogging.factory.cast_string("True")
        )
