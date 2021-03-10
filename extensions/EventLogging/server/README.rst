EventLogging
============

This module contains scripts for processing streams of events generated
by EventLogging_, a MediaWiki extension for logging structured data from
client-side code.

To install dependencies in Ubuntu / Debian, simply run::

    $ sudo apt-get install -y python-coverage python-mysqldb python-nose \
         python-pip python-sqlalchemy python-zmq python-pymongo

.. _EventLogging: https://www.mediawiki.org/wiki/Extension:EventLogging

The file ``setup.py`` lists the numerous dependencies under
``install_requires``. Running ``setup.py install`` configures the
server/eventlogging library and adds the programs in server/bin to your
path.

Daemon Logging
--------------
By default, eventlogging logs at INFO level.  Set the environment variable LOG_LEVEL
if you wish to change this to a differnet level.  E.g.
  export LOG_LEVEL=DEBUG && bin/eventlogging-processor ...
