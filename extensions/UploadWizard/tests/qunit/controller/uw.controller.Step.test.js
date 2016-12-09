/*
 * This file is part of the MediaWiki extension DeedWizard.
 *
 * DeedWizard is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * DeedWizard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DeedWizard.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( uw, $ ) {
	QUnit.module( 'mw.uw.controller.Step', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity test', 2, function ( assert ) {
		var step = new uw.controller.Step( { on: $.noop }, {} );
		assert.ok( step );
		assert.ok( step.ui );
	} );

}( mediaWiki.uploadWizard, jQuery ) );
