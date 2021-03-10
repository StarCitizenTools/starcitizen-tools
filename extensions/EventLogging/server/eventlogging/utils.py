# -*- coding: utf-8 -*-
"""
  eventlogging.utils
  ~~~~~~~~~~~~~~~~~~

  This module contains generic routines that aren't associated with
  a particular function.

"""
from __future__ import unicode_literals

import copy
import logging
import re
import os
import sys
import threading
import traceback

from .compat import items, monotonic_clock, urisplit, urlencode, parse_qsl
from .factory import get_reader, cast_string


__all__ = ('EventConsumer', 'PeriodicThread', 'flatten', 'is_subset_dict',
           'setup_logging', 'unflatten', 'update_recursive',
           'uri_delete_query_item', 'uri_append_query_items', 'uri_force_raw',
           'parse_etcd_uri')


class PeriodicThread(threading.Thread):
    """Represents a threaded job that runs repeatedly at a regular interval."""

    def __init__(self, interval, *args, **kwargs):
        self.interval = interval
        self.ready = threading.Event()
        self.stopping = threading.Event()
        self.logger = logging.getLogger('Log')
        super(PeriodicThread, self).__init__(*args, **kwargs)

    def run(self):
        while not self.stopping.is_set():
            try:
                # Run the target function. Check the clock before
                # and after to determine how long it took to run.
                time_start = monotonic_clock()
                self._Thread__target(*self._Thread__args,
                                     **self._Thread__kwargs)
                time_stop = monotonic_clock()

                run_duration = time_stop - time_start

                # Subtract the time it took the target function to run
                # from the desired run interval. The result is how long
                # we have to sleep before the next run.
                time_to_next_run = self.interval - run_duration
                self.logger.debug('Run duration of thread execution: %s',
                                  str(run_duration))
                if self.ready.wait(time_to_next_run):
                    # If the internal flag of `self.ready` was set, we were
                    # interrupted mid-nap to run immediately. But before we
                    # do, we reset the flag.
                    self.ready.clear()
            except Exception, e:
                trace = traceback.format_exc()
                self.logger.warn('Child thread exiting, exception %s', trace)
                raise e

    def stop(self):
        """Graceful stop: stop once the current iteration is complete."""
        self.stopping.set()
        self.logger.info('Stopping child thread gracefully')


def uri_delete_query_item(uri, key):
    """Delete a key-value pair (specified by key) from a URI's query string."""
    def repl(match):
        separator, trailing_ampersand = match.groups()
        return separator if trailing_ampersand else ''
    return re.sub('([?&])%s=[^&]*(&?)' % re.escape(key), repl, uri)


def uri_append_query_items(uri, params):
    """
    Appends uri with the dict params as key=value pairs using
    urlencode and returns the result.
    """
    return "{0}{1}{2}".format(
        uri,
        '&' if urisplit(uri).query else '?',
        urlencode(params)
    )


def uri_force_raw(uri):
    """
    Returns a uri that sets raw=True as a query param if it isn't already set.
    """
    if 'raw=True' not in uri:
        return uri_append_query_items(uri, {'raw': True})
    else:
        return uri


def is_subset_dict(a, b):
    """True if every key-value pair in `a` is also in `b`.
    Values in `a` which are themselves dictionaries are tested
    by recursively calling :func:`is_subset_dict`."""
    for key, a_value in items(a):
        try:
            b_value = b[key]
        except KeyError:
            return False
        if isinstance(a_value, dict) and isinstance(b_value, dict):
            if not is_subset_dict(a_value, b_value):
                return False
        elif a_value != b_value:
            return False
    return True


def update_recursive(d, other):
    """Recursively update a dict with items from another dict."""
    for key, val in items(other):
        if isinstance(val, dict):
            val = update_recursive(d.get(key, {}), val)
        d[key] = val
    return d


def flatten(d, sep='_', f=None):
    """Collapse a nested dictionary. `f` specifies an optional mapping
    function to apply to each key, value pair. This function is the inverse
    of :func:`unflatten`."""
    flat = []
    for k, v in items(d):
        if f is not None:
            (k, v) = f((k, v))
        if isinstance(v, dict):
            nested = items(flatten(v, sep, f))
            flat.extend((k + sep + nk, nv) for nk, nv in nested)
        else:
            flat.append((k, v))
    return dict(flat)


def unflatten(d, sep='_', f=None):
    """Expand a flattened dictionary. Keys containing `sep` are split into
    nested key selectors. `f` specifies an optional mapping function to apply
    to each key-value pair. This function is the inverse of :func:`flatten`."""
    unflat = {}
    for k, v in items(d):
        if f is not None:
            (k, v) = f((k, v))
        while sep in k:
            k, nested_k = k.split(sep, 1)
            v = {nested_k: v}
        if isinstance(v, dict):
            v = unflatten(v, sep)
        update_recursive(unflat, {k: v})
    return unflat


class EventConsumer(object):
    """An EventLogging consumer API for standalone scripts.

    .. code-block::

       event_stream = eventlogging.EventConsumer('tcp://localhost:8600')
       for event in event_stream.filter(schema='NavigationTiming'):
           print(event)

    """

    def __init__(self, url):
        self.url = url
        self.conditions = {}

    def filter(self, **conditions):
        """Return a copy of this consumer that will filter events based
        on conditions expressed as keyword arguments."""
        update_recursive(conditions, self.conditions)
        filtered = copy.copy(self)
        filtered.conditions = conditions
        return filtered

    def __iter__(self):
        """Iterate events matching the filter."""
        for event in get_reader(self.url):
            if is_subset_dict(self.conditions, event):
                yield event


def parse_etcd_uri(etcd_uri):
    """
    Parses an eventlogging formed URI and returns a kwargs dict suitable
    for passing to etcd.client.Client().
    """
    # etcd_uri should look like:
    # http(s)://hostA:1234,hostB:2345?allow_reconnect=True ...
    parts = urisplit(etcd_uri)
    etcd_kwargs = {
        k: cast_string(v) for k, v in
        items(dict(parse_qsl(parts.query)))
    }

    etcd_kwargs['protocol'] = parts.scheme
    # Convert the host part of uri into
    # a tuple of the form:
    # (('hostA', 1234), ('hostB', 1234))
    etcd_kwargs['host'] = tuple([
        (h.split(':')[0], int(h.split(':')[1]))
        for h in parts.netloc.split(',')
    ])
    return etcd_kwargs


def setup_logging():
    eventlogging_log_level = getattr(
        logging, os.environ.get('LOG_LEVEL', 'INFO')
    )
    logging.basicConfig(stream=sys.stderr, level=eventlogging_log_level,
                        format='%(asctime)s (%(threadName)-10s) %(message)s')

    # Set module logging level to INFO, DEBUG is too noisy.
    logging.getLogger("kafka").setLevel(logging.INFO)
    logging.getLogger("pykafka").setLevel(logging.INFO)
    logging.getLogger("kazoo").setLevel(logging.INFO)
