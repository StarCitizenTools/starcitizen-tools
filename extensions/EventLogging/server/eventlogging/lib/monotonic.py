# -*- coding: utf-8 -*-
"""
  monotonic
  ~~~~~~~~~

  This module provides a ``monotonic()`` function which returns the
  value (in fractional seconds) of a clock which never goes backwards.

  On Python 3.3 or newer, ``monotonic`` will be an alias of
  ``time.monotonic`` from the standard library. On older versions,
  it will fall back to an equivalent implementation:

  +-------------+--------------------+
  | Linux, BSD  | clock_gettime(3)   |
  +-------------+--------------------+
  | Windows     | GetTickCount64     |
  +-------------+--------------------+
  | OS X        | mach_absolute_time |
  +-------------+--------------------+

  If no suitable implementation exists for the current platform,
  attempting to import this module (or to import from it) will
  cause a RuntimeError exception to be raised.


  Copyright 2014 Ori Livneh <ori@wikimedia.org>

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

"""
from __future__ import absolute_import, division

import ctypes
import ctypes.util
import os
import sys
import time


__all__ = ('monotonic',)

try:
    monotonic = time.monotonic
except AttributeError:
    try:
        if sys.platform == 'darwin':  # OS X, iOS
            # See Technical Q&A QA1398 of the Mac Developer Library:
            #  <https://developer.apple.com/library/mac/qa/qa1398/>
            libc = ctypes.CDLL('libc.dylib', use_errno=True)

            class mach_timebase_info_data_t(ctypes.Structure):
                """System timebase info. Defined in <mach/mach_time.h>."""
                _fields_ = (('numer', ctypes.c_uint32),
                            ('denom', ctypes.c_uint32))

            mach_absolute_time = libc.mach_absolute_time
            mach_absolute_time.restype = ctypes.c_uint64

            timebase = mach_timebase_info_data_t()
            libc.mach_timebase_info(ctypes.byref(timebase))
            ticks_per_second = timebase.numer / timebase.denom * 1.0e9

            def monotonic():
                """Monotonic clock, cannot go backward."""
                return mach_absolute_time() / ticks_per_second

        elif sys.platform.startswith('win32'):
            # Windows Vista / Windows Server 2008 or newer.
            GetTickCount64 = ctypes.windll.kernel32.GetTickCount64
            GetTickCount64.restype = ctypes.c_ulonglong

            def monotonic():
                """Monotonic clock, cannot go backward."""
                return GetTickCount64() / 1000.0

        else:
            try:
                clock_gettime = ctypes.CDLL(ctypes.util.find_library('c'),
                                            use_errno=True).clock_gettime
            except AttributeError:
                clock_gettime = ctypes.CDLL(ctypes.util.find_library('rt'),
                                            use_errno=True).clock_gettime

            class timespec(ctypes.Structure):
                """Time specification, as described in clock_gettime(3)."""
                _fields_ = (('tv_sec', ctypes.c_long),
                            ('tv_nsec', ctypes.c_long))

            ts = timespec()

            if sys.platform.startswith('linux'):
                CLOCK_MONOTONIC = 1
            elif sys.platform.startswith('freebsd'):
                CLOCK_MONOTONIC = 4
            elif 'bsd' in sys.platform:
                CLOCK_MONOTONIC = 3

            def monotonic():
                """Monotonic clock, cannot go backward."""
                if clock_gettime(CLOCK_MONOTONIC, ctypes.pointer(ts)):
                    errno = ctypes.get_errno()
                    raise OSError(errno, os.strerror(errno))
                return ts.tv_sec + ts.tv_nsec / 1.0e9

        # Perform a sanity-check.
        if monotonic() - monotonic() >= 0:
            raise ValueError('monotonic() is not monotonic!')

    except Exception:
        raise RuntimeError('no suitable implementation for this system')
