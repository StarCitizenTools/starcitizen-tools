# -*- coding: utf-8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.utils`.

"""
from __future__ import unicode_literals

import unittest

import eventlogging


class FlattenUnflattenTestCase(unittest.TestCase):
    """Test cases for :func:`eventlogging.utils.flatten` and
    :func:`eventlogging.utils.unflatten`."""

    deep = {'k1': 'v1', 'k2': 'v2', 'k3': {'k3a': {'k3b': 'v3b'}}}
    flat = {'k1': 'v1', 'k2': 'v2', 'k3_k3a_k3b': 'v3b'}

    def test_flatten(self):
        """``flatten`` flattens a dictionary with nested dictionary values."""
        flattened = eventlogging.utils.flatten(self.deep)
        self.assertEqual(flattened, self.flat)

    def test_unflatten(self):
        """``unflatten`` makes a flattened dictionary deep again."""
        unflattened = eventlogging.utils.unflatten(self.flat)
        self.assertEqual(unflattened, self.deep)

    def test_flatten_unflatten_inverses(self):
        """``flatten`` and ``unflatten`` are inverse functions."""
        self.assertEqual(eventlogging.utils.flatten(
            eventlogging.utils.unflatten(self.flat)), self.flat)
        self.assertEqual(eventlogging.utils.unflatten(
            eventlogging.utils.flatten(self.deep)), self.deep)


class UtilsTestCase(unittest.TestCase):
    """Test case for :module:`eventlogging.utils`."""

    def test_uri_delete_query_item(self):
        """``uri_delete_query_item`` deletes a query item from a URL."""
        uri = 'http://www.com?aa=aa&bb=bb&cc=cc'
        test_data = (
            ('aa', 'http://www.com?bb=bb&cc=cc'),
            ('bb', 'http://www.com?aa=aa&cc=cc'),
            ('cc', 'http://www.com?aa=aa&bb=bb'),
        )
        for key, expected_uri in test_data:
            actual_uri = eventlogging.uri_delete_query_item(uri, key)
            self.assertEqual(actual_uri, expected_uri)

    def test_update_recursive(self):
        """``update_recursive`` updates a dictionary recursively."""
        target = {'k1': {'k2': {'k3': 'v3'}}}
        source = {'k1': {'k2': {'k4': 'v4'}}}
        result = {'k1': {'k2': {'k3': 'v3', 'k4': 'v4'}}}
        eventlogging.utils.update_recursive(target, source)
        self.assertEqual(target, result)

    def test_is_subset_dict(self):
        """``is_subset_dict`` can tell whether a dictionary is a subset
        of another dictionary."""
        map = {'k1': {'k2': 'v2', 'k3': 'v3'}, 'k4': 'v4'}
        subset = {'k1': {'k3': 'v3'}}
        not_subset = {'k1': {'k4': 'v4'}}
        self.assertTrue(eventlogging.utils.is_subset_dict(subset, map))
        self.assertFalse(eventlogging.utils.is_subset_dict(not_subset, map))

    def test_parse_etcd_uri(self):
        """`parse_etcd_uri` returns proper kwargs from uri"""
        etcd_uri = 'https://hostA:123,hostB:234?' \
                   'cert=/path/to/cert&allow_redirect=True'
        print("URI %s" % etcd_uri)

        etcd_kwargs = eventlogging.utils.parse_etcd_uri(etcd_uri)
        expected_kwargs = {
            'protocol': 'https',
            'host': (('hostA', 123), ('hostB', 234)),
            'cert': '/path/to/cert',
            'allow_redirect': True
        }
        for key in expected_kwargs.keys():
            self.assertEqual(etcd_kwargs[key], expected_kwargs[key])
