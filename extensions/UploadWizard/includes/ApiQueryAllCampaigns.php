<?php
/**
 *
 *
 * Copyright © 2013 Yuvi Panda <yuvipanda@gmail.com>
 *
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

/**
 * Query module to enumerate all registered campaigns
 *
 * @ingroup API
 */
class ApiQueryAllCampaigns extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'uwc' );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$limit = $params['limit'];

		$this->addTables( 'uw_campaigns' );

		$this->addWhereIf( array( 'campaign_enabled' => 1 ), $params['enabledonly'] );
		$this->addOption( 'LIMIT', $limit + 1 );
		$this->addOption( 'ORDER BY', 'campaign_id' ); // Not sure if required?

		$this->addFields( array(
			'campaign_id',
			'campaign_name',
			'campaign_enabled'
		) );

		if ( !is_null( $params['continue'] ) ) {
			$from_id = (int)$params['continue'];
			// Not SQL Injection, since we already force this to be an integer
			$this->addWhere( "campaign_id >= $from_id" );
		}

		$res = $this->select( __METHOD__ );

		$result = $this->getResult();

		$count = 0;

		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				// We have more results than $limit. Set continue
				$this->setContinueEnumParameter( 'continue', $row->campaign_id );
				break;
			}

			$campaign = UploadWizardCampaign::newFromName( $row->campaign_name );

			$campaignPath = array( 'query', $this->getModuleName(), $row->campaign_id );

			$result->addValue(
				$campaignPath,
				'*',
				json_encode( $campaign->getParsedConfig() )
			);
			$result->addValue(
				$campaignPath,
				'name',
				$campaign->getName()
			);
			$result->addValue(
				$campaignPath,
				'trackingCategory',
				$campaign->getTrackingCategory()->getDBKey()
			);
			$result->addValue(
				$campaignPath,
				'totalUploads',
				$campaign->getUploadedMediaCount()
			);
			if ( UploadWizardConfig::getSetting( 'campaignExpensiveStatsEnabled' ) === true ) {
				$result->addValue(
					$campaignPath,
					'totalContributors',
					$campaign->getTotalContributorsCount()
				);
			}
		}
		if ( defined( 'ApiResult::META_CONTENT' ) ) {
			$result->addIndexedTagName( array( 'query', $this->getModuleName() ), 'campaign' );
		} else {
			$result->setIndexedTagName_internal( array( 'query', $this->getModuleName() ), 'campaign' );
		}
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams() {
		return array(
			'enabledonly' => false,
			'limit' => array(
				ApiBase::PARAM_DFLT => 50,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			),
			'continue' => array(
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			),
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=query&list=allcampaigns&uwcenabledonly='
				=> 'apihelp-query+allcampaigns-example-1',
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:UploadWizard/API';
	}
}
