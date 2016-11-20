<?php
/**
 */

class ReplaceTextHooks {

	public static function addToAdminLinks( ALTree &$adminLinksTree ) {
		$generalSection = $adminLinksTree->getSection( wfMessage( 'adminlinks_general' )->text() );
		$extensionsRow = $generalSection->getRow( 'extensions' );

		if ( is_null( $extensionsRow ) ) {
			$extensionsRow = new ALRow( 'extensions' );
			$generalSection->addRow( $extensionsRow );
		}

		$extensionsRow->addItem( ALItem::newFromSpecialPage( 'ReplaceText' ) );

		return true;
	}

}
