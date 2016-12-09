<?php
/**
 * Fixes timestamp corruption caused by one or more webservers temporarily
 * being set to the wrong time.
 * The time offset must be known and consistent. Start and end times
 * (in 14-character format) restrict the search, and must bracket the damage.
 * There must be a majority of good timestamps in the search period.
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
 * @ingroup Maintenance
 * @author Yuvi Panda <yuvipanda@gmail.com>
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to migrate campaigns from older, database table
 * to newer page based storage
 *
 * @ingroup Maintenance
 */
class MigrateCampaigns extends Maintenance {

	/**
	 * @var DatabaseBase
	 */
	private $dbr = null;

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Migrate UploadCampaigns from database storage to pages";
		$this->addOption( 'user', 'The user to perform the migration as', false, true, 'u' );
	}

	private $oldKeyDefaults = array(
		'headerLabelPage' => '',
		'thanksLabelPage' => '',

		'defaultLicenseType' => 'choice',
		'ownWorkOption' => 'choice',
		'licensesOwnWork' => '',
		'defaultOwnWorkLicense' => '',

		'skipTutorial' => false,
		'tutorialTemplate' => 'Licensing_tutorial_$1.svg',
		'tutorialWidth' => 720,
		'tutorialHelpdeskCoords' => '27, 1319, 691, 1384',

		'autoWikiText' => '',
		'autoCategories' => '',

		'defaultDescription' => '',
		'defaultLat' => '',
		'defaultLon' => '',
		'defaultAlt' => '',
		'defaultCategories' => '',

		'idField' => '',
		'idFieldLabel' => '',
		'idFieldLabelPage' => '',
		'idFieldMaxLength' => 25,
		'idFieldInitialValue' => '',

		'idField2' => '',
		'idField2Label' => '',
		'idField2LabelPage' => '',
		'idField2MaxLength' => 25,
		'idField2InitialValue' => ''
	);

	private $oldNumberConfigs = array(
		'idFieldMaxLength',
		'idField2MaxLength',
		'tutorialWidth',
		'defaultLat',
		'defaultLon',
		'defaultAlt'
	);

	/**
	 * @param $id int|string
	 * @return array
	 */
	private function getConfigFromDB( $id ) {
		$config = array();

		$confProps = $this->dbr->select(
			'uw_campaign_conf',
			array( 'cc_property', 'cc_value' ),
			array( 'cc_campaign_id' => $id ),
			__METHOD__
		);

		foreach ( $confProps as $confProp ) {
			if ( in_array( $confProp->cc_property, $this->oldNumberConfigs ) ) {
				$config[$confProp->cc_property] = intval( $confProp->cc_value );
			} else {
				$config[$confProp->cc_property] = $confProp->cc_value;
			}
		}

		$mergedConfig = array();

		foreach ( $this->oldKeyDefaults as $key => $default ) {
			if ( array_key_exists( $key, $config ) && $config[$key] !== $default ) {
				$mergedConfig[$key] = $config[$key];
			} else {
				$mergedConfig[$key] = null;
			}
		}

		return $mergedConfig;
	}

	/**
	 * @param $string string
	 * @return array
	 */
	private function explodeStringToArray( $string ) {
		$parts = explode( '|', $string );
		$array = array();

		foreach ( $parts as $part ) {
			$part = trim( $part );

			if ( $part !== '' ) {
				$array[] = $part;
			}
		}
		return $array;
	}

	/**
	 * @param $array array
	 * @return array
	 */
	private function trimArray( $array ) {
		$newArray = array();
		foreach ( $array as $key => $value ) {
			if ( gettype( $value ) === 'array' ) {
				$trimmedValue = $this->trimArray( $value );
				if ( $trimmedValue !== array() ) {
					$newArray[$key] = $trimmedValue;
				}
			} else {
				if ( $value !== null ) {
					$newArray[$key] = $value;
				}
			}
		}
		return $newArray;
	}

	/**
	 * Ensure that the default license, if set, is the first
	 *
	 * @param $licenses array
	 * @param $default string
	 * @return array
	 */
	private function ensureDefaultLicense( $licenses, $default ) {
		if ( count( $licenses ) === 1 || ( $default === null || trim( $default ) === '' ) ) {
			return $licenses;
		}
		if ( in_array( $default, $licenses ) ) {
			array_splice( $licenses, array_search( $default, $licenses ), 1 );
		}
		array_unshift( $licenses, $default );
		// FIXME: No return value
	}

	/**
	 * @param $campaign
	 * @param $oldConfig array
	 * @return array
	 */
	private function getConfigForJSON( $campaign, $oldConfig ) {
		$config = array(
			'enabled' => $campaign->campaign_enabled === '1',
			'display' => array(
				'headerLabelPage' => $oldConfig['headerLabelPage'],
				'thanksLabelPage' => $oldConfig['thanksLabelPage']
			),
			'defaults' => array(
				'description' => $oldConfig['defaultDescription'],
				'lat' => $oldConfig['defaultLat'],
				'lon' => $oldConfig['defaultLon'],
				'categories' => $this->explodeStringToArray( $oldConfig['defaultCategories'] )
			),
			'autoAdd' => array(
				'wikitext' => $oldConfig['autoWikiText'],
				'categories' => $this->explodeStringToArray( $oldConfig['autoCategories'] )
			),
			"licensing" => array(
				'defaultType' => $oldConfig['defaultLicenseType'],
				'ownWorkDefault' => $oldConfig['ownWorkOption'],
				'ownWork' => array(
					'licenses' => $this->ensureDefaultLicense(
						$this->explodeStringToArray( $oldConfig['licensesOwnWork'] ),
						$oldConfig['defaultOwnWorkLicense']
					)
				)
			),
			'fields' => array(
				array(
					'wikitext' => $oldConfig['idField'],
					'label' => $oldConfig['idFieldLabel'],
					# Migrated even though this is a nop.
					# People will have to migrate this manually
					'labelPage' => $oldConfig['idFieldLabelPage'],
					'maxLength' => $oldConfig['idFieldMaxLength'],
					'initialValue' => $oldConfig['idFieldInitialValue']
				),
				array(
					'wikitext' => $oldConfig['idField2'],
					'label' => $oldConfig['idField2Label'],
					'labelPage' => $oldConfig['idField2LabelPage'],
					'maxLength' => $oldConfig['idField2MaxLength'],
					'initialValue' => $oldConfig['idField2InitialValue']
				)
			),
			'tutorial' => array(
				'skip' => (bool)$oldConfig['skipTutorial'],
				'template' => $oldConfig['tutorialTemplate'],
				'helpdeskCoords' => $oldConfig['tutorialHelpdeskCoords'],
				'width' => $oldConfig['tutorialWidth']
			)
		);

		return $this->trimArray( $config );
	}

	public function execute() {
		$user = $this->getOption( 'user', 'Maintenance script' );

		$this->dbr = wfGetDB( DB_MASTER );
		$campaigns = $this->dbr->select(
			'uw_campaigns',
			'*'
		);

		if ( !$campaigns->numRows() ) {
			$this->output( "Nothing to migrate.\n" );
			return;
		}

		foreach ( $campaigns as $campaign ) {
			$oldConfig = $this->getConfigFromDB( $campaign->campaign_id );
			$newConfig = $this->getConfigForJSON( $campaign, $oldConfig );

			$title = Title::makeTitleSafe( NS_CAMPAIGN, $campaign->campaign_name );
			$page = Wikipage::factory( $title );

			$content = new CampaignContent( json_encode( $newConfig ) );
			$page->doEditContent(
				$content,
				"Migrating from old campaign tables",
				0, false,
				User::newFromName( $user )
			);
			$this->output( "Migrated {$campaign->campaign_name}\n" );
		}
	}
}

$maintClass = "MigrateCampaigns";
require_once RUN_MAINTENANCE_IF_MAIN;
