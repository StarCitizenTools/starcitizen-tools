# -*- coding: utf-8 -*-
"""
  eventlogging
  ~~~~~~~~~~~~

  This module collects the public members of each :mod:`eventlogging`
  submodule into a single namespace. It thus corresponds to the
  package's public API.

  .. note:: To avoid circular imports, no submodule should import
            anything from here.

  :copyright: (c) 2012 by Ori Livneh
  :license: GNU General Public Licence 2.0 or later

"""
# flake8: noqa

from .compat import *
from .factory import *
from .handlers import *
from .jrm import *
from .parse import *
from .schema import *
from .streams import *
from .crypto import *
from .utils import *

# The fact that schema validation is entrusted to a third-party module
# is an implementation detail that a consumer of this package's API
# should not have to know or care about. We thus provide package-local
# bindings for :exc:`jsonschema.ValidationError` and
# :exc:`jsonschema.SchemaError`.
from jsonschema import ValidationError, SchemaError

__version__ = '0.9'

# Alias :class:`EventConsumer` as `connect'.
connect = EventConsumer
