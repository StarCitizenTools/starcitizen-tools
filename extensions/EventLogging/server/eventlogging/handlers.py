# -*- coding: utf-8 -*-
"""
  eventlogging.handlers
  ~~~~~~~~~~~~~~~~~~~~~

  This class contains the set of event readers and event writers that ship with
  EventLogging. Event readers are generators that yield successive events from
  a stream. Event writers are coroutines that receive events and handle them
  somehow. Both readers and writers are designed to be configurable using URIs.
  :func:`eventlogging.drive` pumps data through a reader-writer pair.

"""
import collections
import datetime
import glob
import imp
import inspect
import json

from functools import partial
from kafka import KafkaClient
from kafka import KeyedProducer
from kafka import SimpleProducer
from kafka.producer.base import Producer
from kafka.common import KafkaTimeoutError
from pykafka import KafkaClient as PyKafkaClient
from pykafka import BalancedConsumer

import logging
import logging.handlers
import os
import re
import socket
import sys
import sqlalchemy
import statsd
import time
import traceback
import uuid

from .compat import items
from .utils import PeriodicThread, uri_delete_query_item
from .factory import writes, reads
from .streams import stream, pub_socket, sub_socket, udp_socket
from .jrm import store_sql_events, DB_FLUSH_INTERVAL

__all__ = ('load_plugins',)

# EventLogging will attempt to load the configuration file specified in the
# 'EVENTLOGGING_PLUGIN_DIR' environment variable if it is defined. If it is
# not defined, EventLogging will default to the value specified below.
DEFAULT_PLUGIN_DIR = '/usr/local/lib/eventlogging'


def load_plugins(path=None):
    """Load EventLogging plug-ins from `path`. Plug-in module names are mangled
    to prevent clobbering modules in the Python module search path."""
    if path is None:
        path = os.environ.get('EVENTLOGGING_PLUGIN_DIR', DEFAULT_PLUGIN_DIR)
    for plugin in glob.glob(os.path.join(path, '*.py')):
        imp.load_source('__eventlogging_plugin_%x__' % hash(plugin), plugin)


#
# Writers
#

@writes('mongodb')
def mongodb_writer(uri, database='events'):
    import pymongo

    client = pymongo.MongoClient(uri)
    db = client[database]
    datetime_from_timestamp = datetime.datetime.fromtimestamp

    while 1:
        event = (yield)
        event['timestamp'] = datetime_from_timestamp(event['timestamp'])
        event['_id'] = event['uuid']
        collection = event['schema']
        db[collection].insert(event)


@writes('kafka')
def kafka_writer(
    path,
    producer='simple',
    topic='eventlogging_%(schema)s',
    key='%(schema)s_%(revision)s',
    blacklist=None,
    raw=False,
    **kafka_producer_args
):
    """
    Write events to Kafka.

    Kafka URIs look like:
    kafka:///b1:9092,b2:9092?topic=eventlogging_%s(schema)&async=True&...

    This producer uses either SimpleProducer or KeyedProducer from
    kafka-python.  You may pass any configs that base Producer takes
    as keyword arguments via URI query params.

    NOTE:  If you do not explicitly set it, async will default to True.

        path      - URI path should be comma separated Kafka Brokers.
                    e.g. kafka01:9092,kafka02:9092,kafka03:9092

        producer  - Either 'keyed' or 'simple'.  Default: 'simple'.

        topic     - Python format string topic name.
                    If the incoming event is a dict (not a raw string)
                    topic will be interpolated against event.  I.e.
                    topic % event.  Default: eventlogging_%(schema)s

        key       - Python format string key of the event message in Kafka.
                    If the incoming event is a dict (not a raw string)
                    key will be interpolated against event.  I.e.
                    key % event.  Default: %(schema)s_%(revision)s.
                    This is ignored if you are using the simple producer.

        blacklist - Pattern string matching a list of schemas that should not
                    be written. This is useful to keep high volume schemas
                    from being written to an output stream.  This will
                    be ignored if the incoming events are raw.

        raw       - Should the events be written as raw (encoded) or not?
    """

    # Brokers should be in the uri path
    brokers = path.strip('/')

    # remove non Kafka Producer args from kafka_consumer_args
    kafka_producer_args = {
        k: v for k, v in items(kafka_producer_args)
        if k in inspect.getargspec(Producer.__init__).args
    }

    # Use async producer by default
    if 'async' not in kafka_producer_args:
        kafka_producer_args['async'] = True

    kafka = KafkaClient(brokers)

    if producer == 'keyed':
        ProducerClass = KeyedProducer
    else:
        ProducerClass = SimpleProducer

    kafka_producer = ProducerClass(kafka, **kafka_producer_args)

    # These will be used if incoming events are not interpolatable.
    default_topic = topic.encode('utf8')
    default_key = key.encode('utf8')

    kafka_topic_create_timeout_seconds = 0.1

    if blacklist:
        blacklist_pattern = re.compile(blacklist)
    else:
        blacklist_pattern = None

    while 1:
        event = (yield)

        # If event is a dict (not Raw) then we can interpolate topic and key
        # as format strings.
        # E.g. message_topic = 'eventlogging_%(schema)s' % event.
        # WARNING!  Be sure that your topic and key strings don't try
        # to interpolate out a field in event that doesn't exist!
        if isinstance(event, dict):
            if blacklist_pattern and blacklist_pattern.match(event['schema']):
                logging.debug(
                    '%s is blacklisted, not writing event %s.' %
                    (event['schema'], event['uuid'])
                )
                continue

            message_topic = (topic % event).encode('utf8')
            if producer == 'keyed':
                message_key = (key % event).encode('utf8')
        else:
            message_topic = default_topic
            message_key = default_key

        try:
            # Make sure this topic exists before we attempt to produce to it.
            # This call will timeout in kafka_topic_create_timeout_seconds.
            # This should return faster than this if this kafka client has
            # already cached topic metadata for this topic.  Otherwise
            # it will try to ask Kafka for it each time.  Make sure
            # auto.create.topics.enabled is true for your Kafka cluster!
            kafka.ensure_topic_exists(
                message_topic,
                kafka_topic_create_timeout_seconds
            )
        except KafkaTimeoutError:
            error_message = "Failed to ensure Kafka topic %s exists " \
                "in %f seconds when producing event" % (
                    message_topic,
                    kafka_topic_create_timeout_seconds
                )
            if isinstance(event, dict):
                error_message += " of schema %s revision %d" % (
                    event['schema'],
                    event['revision']
                )
            error_message += ". Skipping event. " \
                "(This might be ok if this is a new topic.)"
            logging.warn(error_message)
            continue

        if raw:
            value = event.encode('utf-8')
        else:
            value = json.dumps(event, sort_keys=True)

        # send_messages() for the different producer types have different
        # signatures.  Call it appropriately.
        if producer == 'keyed':
            kafka_producer.send_messages(message_topic, message_key, value)
        else:
            kafka_producer.send_messages(message_topic, value)


def insert_stats(stats, inserted_count):
    """
    Callback function to increment mysql inserted metric in statsd,
    that is called after successful insertion of events into mysql.

        stats           - Instance of stats.StatsClient
        inserted_count  - Number of events that have been inserted
    """
    if stats:
        stats.incr('overall.inserted', inserted_count)


@writes('mysql', 'sqlite')
def sql_writer(uri, replace=False, statsd_host=''):
    """Writes to an RDBMS, creating tables for SCIDs and rows for events."""
    # Don't pass 'replace' and 'statsd_host' parameter to SQLAlchemy.
    uri = uri_delete_query_item(uri, 'replace')
    uri = uri_delete_query_item(uri, 'statsd_host')

    logger = logging.getLogger('Log')

    # Create a statsd client instance if statsd_host is specified
    stats = None
    if statsd_host:
        stats = statsd.StatsClient(statsd_host, 8125, prefix='eventlogging')

    meta = sqlalchemy.MetaData(bind=uri)
    # Each scid stores a buffer and the timestamp of the first insertion.
    events = collections.defaultdict(lambda: ([], time.time()))
    events_batch = collections.deque()
    # Since the worker is unaware of the statsd host, create a partial
    # that binds the statsd client argument to the callback
    worker = PeriodicThread(interval=DB_FLUSH_INTERVAL,
                            target=store_sql_events,
                            args=(meta, events_batch),
                            kwargs={'replace': replace,
                                    'on_insert_callback':
                                        partial(insert_stats, stats)})
    worker.start()

    if meta.bind.dialect.name == 'mysql':
        @sqlalchemy.event.listens_for(sqlalchemy.pool.Pool, 'checkout')
        def ping(dbapi_connection, connection_record, connection_proxy):
            # Just before executing an insert, call mysql_ping() to verify
            # that the connection is alive, and reconnect if necessary.
            dbapi_connection.ping(True)
    try:
        batch_size = 5000
        batch_time = 300  # in seconds
        # Max number of batches pending insertion.
        queue_size = 1000
        sleep_seconds = 5
        # Link the main thread to the worker thread so we
        # don't keep filling the queue if the worker died.
        while worker.is_alive():
            # If the queue is too big, wait for the worker to empty it.
            while len(events_batch) > queue_size:
                logger.info('Sleeping %d seconds', sleep_seconds)
                time.sleep(sleep_seconds)
            event = (yield)
            # Break the event stream by schema (and revision)
            scid = (event['schema'], event['revision'])
            scid_events, first_timestamp = events[scid]
            scid_events.append(event)
            if stats:
                stats.incr('overall.insertAttempted')
            # Check if the schema queue is too long or too old
            if (len(scid_events) >= batch_size or
                    time.time() - first_timestamp >= batch_time):
                logger.info('%s_%s queue is large or old, flushing', *scid)
                events_batch.append((scid, scid_events))
                del events[scid]
    except GeneratorExit:
        # Allow the worker to complete any work that is
        # already in progress before shutting down.
        logger.info('Stopped main thread via GeneratorExit')
        logger.info('Events when stopped %s', len(events))
        worker.stop()
        worker.join()
    except Exception:
        t = traceback.format_exc()
        logger.warn('Exception caught %s', t)
        raise
    finally:
        # If there are any events remaining in the queue,
        # process them in the main thread before exiting.
        for scid, (scid_events, _) in events.iteritems():
            events_batch.append((scid, scid_events))
        store_sql_events(meta, events_batch, replace=replace,
                         on_insert_callback=partial(insert_stats, stats))


@writes('file')
def log_writer(path, raw=False):
    """Write events to a file on disk."""
    handler = logging.handlers.WatchedFileHandler(path)

    # We want to be able to support multiple file writers
    # within a given Python process, so uniquely
    # identify this logger within Python's logging
    # system by the file's path.
    log = logging.getLogger('Events-' + path)

    log.setLevel(logging.INFO)
    log.addHandler(handler)
    # Don't propagate these events to the global logger
    # used by eventlogging.  We don't want eventlogging
    # daemons to print these event logs to stdout or stderr
    # all the time.
    log.propagate = False

    while 1:
        event = (yield)
        if raw:
            log.info(event)
        else:
            log.info(json.dumps(event, sort_keys=True, check_circular=False))


@writes('tcp')
def zeromq_writer(uri, raw=False):
    """Publish events on a ZeroMQ publisher socket."""
    pub = pub_socket(uri)
    while 1:
        event = (yield)
        if raw:
            pub.send_unicode(event)
        else:
            pub.send_unicode(json.dumps(event,
                                        sort_keys=True,
                                        check_circular=False) + '\n')


@writes('statsd')
def statsd_writer(hostname, port, prefix='eventlogging.schema'):
    """Increments StatsD SCID counters for each event."""
    addr = socket.gethostbyname(hostname), port
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    while 1:
        event = (yield)
        stat = prefix + '.%(schema)s:1|c' % event
        sock.sendto(stat.encode('utf-8'), addr)


@writes('stdout')
def stdout_writer(uri, raw=False):
    """Writes events to stdout. Pretty-prints if stdout is a terminal."""
    dumps_kwargs = dict(sort_keys=True, check_circular=False)
    if sys.stdout.isatty():
        dumps_kwargs.update(indent=2)
    while 1:
        event = (yield)
        if raw:
            print(event)
        else:
            print(json.dumps(event, **dumps_kwargs))


@writes('udp')
def udp_writer(hostname, port, raw=False):
    """Writes data to UDP."""
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    while 1:
        event = (yield)
        if raw:
            sock.sendto(event, (hostname, port))
        else:
            sock.sendto(json.dumps(event), (hostname, port))

#
# Readers
#


@reads('stdin')
def stdin_reader(uri, raw=False):
    """Reads data from standard input."""
    return stream(sys.stdin, raw)


@reads('tcp')
def zeromq_subscriber(uri, identity=None, subscribe='', raw=False):
    """Reads data from a ZeroMQ publisher. If `raw` is truthy, reads
    unicode strings. Otherwise, reads JSON."""
    sock = sub_socket(uri, identity=identity, subscribe=subscribe)
    return stream(sock, raw)


@reads('udp')
def udp_reader(hostname, port, raw=False):
    """Reads data from a UDP socket."""
    return stream(udp_socket(hostname, port), raw)


@reads('kafka')
def kafka_reader(
    path,
    topic='eventlogging',
    identity='',
    raw=False,
    **kafka_consumer_args
):
    """
    Reads events from Kafka.

    Kafka URIs look like:
    kafka:///b1:9092,b2:9092?topic=topic_name&identity=consumer_group_name&
    auto_commit_enable=True&auto_commit_interval_ms=1000...

    This reader uses the pykafka BalancedConsumer.  You may pass
    any configs that BalancedConsumer takes as keyword arguments via
    the kafka URI query params.

    The auto_commit_interval_ms is by default 60 seconds. This is pretty high
    and may lead to more duplicate message consumption (Kafka has at atleast
    once message delivery guarantee). Lowering this(to 1 second?) makes sure
    that there aren't as many duplicates, but incurs the overhead of committing
    offsets to zookeeper more often.

    If auto_commit_enable is True, then messages will be marked as done based
    on the auto_commit_interval_ms time period.
    This has the downside of committing message offsets before
    work might be actually complete.  E.g. if inserting into MySQL, and
    the process dies somewhere along the way, it is possible
    that message offsets will be committed to Kafka for messages
    that have not been inserted into MySQL.  Future work
    will have to fix this problem somehow.  Perhaps a callback?
    """
    # The identity param is used to define the consumer group name.
    # If identity is empty create a default unique one. This ensures we don't
    # accidentally put consumers to the same group. Explicitly specify identity
    # to launch consumers in the same consumer group
    identity = identity if identity else 'eventlogging-' + str(uuid.uuid1())

    # Brokers should be in the uri path
    # path.strip returns type 'unicode' and pykafka expects a string, so
    # converting unicode to str
    brokers = path.strip('/').encode('ascii', 'ignore')

    # remove non KafkaConsumer args from kafka_consumer_args
    kafka_consumer_args = {
        k: v for k, v in items(kafka_consumer_args)
        if k in inspect.getargspec(BalancedConsumer.__init__).args
    }

    kafka_client = PyKafkaClient(hosts=brokers)
    kafka_topic = kafka_client.topics[topic]

    consumer = kafka_topic.get_balanced_consumer(
        consumer_group=identity.encode('ascii', 'ignore'),
        **kafka_consumer_args)

    # Define a generator to read from the BalancedConsumer instance
    def message_stream(consumer):
        while True:
            yield consumer.consume()

    return stream((message.value for message in message_stream(consumer)), raw)
