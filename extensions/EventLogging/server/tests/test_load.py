# -*- coding: utf-8 -*-
"""
This script generates a stream of requests to beta-labs
(bits.beta.wmflabs.org) to test EventLogging load limitations.

Thanks to Emilio Monti for the Worker and ThreadPool codes!
https://code.activestate.com/recipes/577187-python-thread-pool/
"""

import json
import time
import string
import random
import urllib
import httplib
import argparse
from threading import Thread
from Queue import Queue


POOL_SIZE = 100
SCHEMA_HOST = 'meta.wikimedia.org'
SCHEMA_URL = ('/w/api.php?action=query&prop=revisions&format=json'
              '&rvprop=content&titles=Schema:%s&revid=%s')
CAPSULE_REVISION = 10981547
EL_URL = '/event.gif?%s;'


class Worker(Thread):
    """Thread executing tasks from a given tasks queue"""
    def __init__(self, tasks):
        Thread.__init__(self)
        self.tasks = tasks
        self.daemon = True
        self.start()

    def run(self):
        while True:
            func, args, kargs = self.tasks.get()
            try:
                func(*args, **kargs)
            except Exception, e:
                print 'Worker error: %s.' % e
            self.tasks.task_done()


class ThreadPool(object):
    """Pool of threads consuming tasks from a queue"""
    def __init__(self, num_threads):
        self.tasks = Queue(num_threads)
        for _ in range(num_threads):
            Worker(self.tasks)

    def add_task(self, func, *args, **kargs):
        """Add a task to the queue"""
        self.tasks.put((func, args, kargs))

    def wait_completion(self):
        """Wait for completion of all the tasks in the queue"""
        self.tasks.join()


class EventGenerator(object):
    """Generates events for a given schema."""
    def __init__(self, schema_name, schema_revision):
        self.schema_name = schema_name
        self.schema_revision = schema_revision
        try:
            self.schema = get_schema(schema_name, schema_revision)
        except Exception:
            raise RuntimeError(
                'Could not retrieve schema information: %s.' % schema_name)

    def generate(self, capsule_schema, optional_values):
        event = self.instantiate(capsule_schema, optional_values)
        event['schema'] = self.schema_name
        event['revision'] = self.schema_revision
        event['timestamp'] = int(time.time())
        event['event'] = self.instantiate(self.schema, optional_values)
        return event

    def instantiate(self, schema, optional_values):
        event = {}
        for name, prop in schema['properties'].iteritems():
            # Decide if the property should be instantiated
            if (prop.get('required', None) or optional_values == 'always' or
                    (optional_values == 'sometimes' and random.random() < .2)):
                # Instantiate depending on kind of property
                if 'enum' in prop:
                    value = random.choice(prop['enum'])
                else:
                    prop_type = prop['type']
                    if prop_type in ['integer', 'number']:
                        value = random.randint(0, 99)
                    elif prop_type == 'boolean':
                        value = random.random() < 0.5
                    elif prop_type == 'string':
                        value = self.random_string(2)
                    elif prop_type == 'object':
                        pass  # only event capsule has that
                    else:
                        raise ValueError(
                            'Unexpected property type: %s' % prop_type)
                event[name] = value
        return event

    def random_string(self, length):
        alphabet = (string.ascii_uppercase + string.digits +
                    string.ascii_lowercase)
        return ''.join(random.choice(alphabet) for _ in range(length))


def get_schema(schema_name, schema_revision):
    conn = httplib.HTTPSConnection(SCHEMA_HOST)
    conn.request("GET", SCHEMA_URL % (schema_name, schema_revision))
    data = json.loads(conn.getresponse().read())
    pages = data['query']['pages']
    page_id = pages.keys()[0]
    schema_str = pages[page_id]['revisions'][0]['*']
    return json.loads(schema_str)


def send_event(event, endpoint):
    query_string = urllib.quote(json.dumps(event))
    conn = httplib.HTTPConnection(endpoint)
    conn.request("GET", EL_URL % query_string)


def get_arguments():
    # Get argparse params.
    ap = argparse.ArgumentParser(
        description='EventLogging load tester',
        fromfile_prefix_chars='@')
    ap.add_argument(
        'events_per_second',
        help='Number of total of events per second that will be sent.',
        default='100')
    ap.add_argument(
        '-s', '--schema',
        help=('Format: "SchemaName:Revision:Share". Example: '
              '"Edit:11448630:0.35". SchemaName and Revision indicate a '
              'schema for which events will be sent. Share indicates the '
              'proportion of events for that schema (Integer or float).'),
        action='append')
    ap.add_argument(
        '--optional-values',
        help=('Indicates when to instantiate optional event fields. '
              'Possible values: "never", "sometimes" and "always".'),
        default='sometimes')
    ap.add_argument(
        '--endpoint',
        help=('Hostname where events should be sent. '
              'E.g. bits.wikimedia.org'),
        default='bits.beta.wmflabs.org')
    args = ap.parse_args()

    # Check and build sleep interval param.
    try:
        events_per_second = int(args.events_per_second)
        sleep_interval = 1.0 / events_per_second
    except ValueError:
        raise ValueError('Invalid parameter events_per_second: %s.' %
                         args.events_per_second)

    # Check and build generators param.
    generators = []
    if args.schema:
        for schema in args.schema:
            try:
                schema_name, schema_revision, schema_share = schema.split(':')
                schema_revision = int(schema_revision)
                schema_share = float(schema_share)
            except ValueError:
                raise ValueError('Invalid parameter -s/--schema: %s.' % schema)
            generator = EventGenerator(schema_name, schema_revision)
            generators.append((generator, schema_share))

    # Check and build optional values param.
    optional_values = 'sometimes'
    if args.optional_values:
        if args.optional_values in ['never', 'sometimes', 'always']:
            optional_values = args.optional_values
        else:
            raise ValueError('Invalid parameter --optional-values: %s.' %
                             args.optional_values)

    return sleep_interval, generators, optional_values, args.endpoint


def weighted_choice(choices):
    total = sum(w for c, w in choices)
    r = random.uniform(0, total)
    upto = 0
    for c, w in choices:
        if upto + w > r:
            return c
        upto += w


def main():
    print 'Initializing...'
    sleep_interval, generators, optional_values, endpoint = get_arguments()
    capsule_schema = get_schema('EventCapsule', CAPSULE_REVISION)
    pool = ThreadPool(POOL_SIZE)
    print 'Sending events...'
    count = 0
    try:
        while True:
            t1 = time.time()
            generator = weighted_choice(generators)
            event = generator.generate(capsule_schema, optional_values)
            pool.add_task(send_event, event, endpoint)
            t2 = time.time()
            count += 1
            time_to_sleep = max(sleep_interval - (t2 - t1), 0)
            time.sleep(time_to_sleep)
    except KeyboardInterrupt:
        print '\n%d events sent, exiting.' % count


if __name__ == '__main__':
    main()
