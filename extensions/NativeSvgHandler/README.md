Information
===========

This is a MediaWiki extension which allows SVG files to by served directly to
clients for client-side rendering. No configuration is required, just add the
following lines to your LocalSettings.php once the source of the extension is
copied to the extensions/ directory:

require_once "$IP/extensions/NativeSvgHandler/NativeSvgHandler.php";
$wgNativeSvgHandlerEnableLinks = true; //Set to false to disable links over SVG images

License
=======

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
