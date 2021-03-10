<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Babel;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiResult;
use Babel;
use User;

class ApiQueryBabel extends ApiQueryBase {
	public function __construct( ApiQuery $queryModule, $moduleName ) {
		parent::__construct( $queryModule, $moduleName, 'bab' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$userName = $params['user'];
		$user = User::newFromName( $userName );
		if ( !$user || !$user->getId() ) {
			$this->dieWithError( [ 'nosuchusershort', wfEscapeWikiText( $userName ) ], 'baduser' );
			return;
		}

		$data = Babel::getUserLanguageInfo( $user );
		// Force a JSON object
		$data[ApiResult::META_TYPE] = 'assoc';

		$this->getResult()->addValue(
			'query',
			$this->getModuleName(),
			$data
		);
	}

	public function getAllowedParams( /* $flags = 0 */ ) {
		return [
			'user' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'user',
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&meta=babel&babuser=Example'
				=> 'apihelp-query+babel-example-1',
		];
	}

}
