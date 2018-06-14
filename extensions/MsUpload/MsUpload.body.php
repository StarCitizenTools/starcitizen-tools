<?php

class MsUpload {

	static function start() {
		global $wgOut, $wgScriptPath, $wgMSU_useMsLinks, $wgMSU_showAutoCat, $wgMSU_checkAutoCat,
			$wgMSU_confirmReplace, $wgMSU_useDragDrop, $wgMSU_imgParams, $wgFileExtensions,
			$wgMSU_uploadsize, $wgMSU_flash_swf_url, $wgMSU_silverlight_xap_url;

		$wgMSU_flash_swf_url = __DIR__ . '/plupload/Moxie.swf';
		$wgMSU_silverlight_xap_url = __DIR__ . '/plupload/Moxie.xap';

		$wgOut->addJsConfigVars( [
			'wgFileExtensions' => array_values( array_unique( $wgFileExtensions ) ),
		] );

		if ( $wgMSU_imgParams ) {
			$wgMSU_imgParams = '|' . $wgMSU_imgParams;
		}

		$msuVars = [
			'scriptPath' => $wgScriptPath,
			'flash_swf_url' => $wgMSU_flash_swf_url,
			'silverlight_xap_url' => $wgMSU_silverlight_xap_url,
			'useDragDrop' => $wgMSU_useDragDrop,
			'showAutoCat' => $wgMSU_showAutoCat,
			'checkAutoCat' => $wgMSU_checkAutoCat,
			'useMsLinks' => $wgMSU_useMsLinks,
			'confirmReplace' => $wgMSU_confirmReplace,
			'imgParams' => $wgMSU_imgParams,
			'uploadsize' => $wgMSU_uploadsize,
		];

		$wgOut->addJsConfigVars( 'msuVars', $msuVars );
		$wgOut->addModules( 'ext.MsUpload' );

		return true;
	}

	static function saveCat( $filename, $category ) {
		global $wgContLang, $wgUser;
		$mediaString = strtolower( $wgContLang->getNsText( NS_FILE ) );
		$title = $mediaString . ':' . $filename;
		$text = "\n[[" . $category . "]]";
		$wgEnableWriteAPI = true;
		$params = new FauxRequest( [
			'action' => 'edit',
			'section' => 'new',
			'title' => $title,
			'text' => $text,
			'token' => $wgUser->editToken(), // $token."%2B%5C",
		], true, $_SESSION );
		$enableWrite = true;
		$api = new ApiMain( $params, $enableWrite );
		$api->execute();
		$data = $api->getResult()->getResultData();
		return $mediaString;

		/* The code below does the same and is better,
			but for some reason it doesn't update the categorylinks table, so it's no good
		global $wgContLang, $wgUser;
		$title = Title::newFromText( $filename, NS_FILE );
		$page = new WikiPage( $title );
		$text = $page->getText();
		$text .= "\n\n[[" . $category . "]]";
		$summary = wfMessage( 'msu-comment' );
		$status = $page->doEditContent( $text, $summary, EDIT_UPDATE, false, $wgUser );
		$value = $status->value;
		$revision = $value['revision'];
		$page->doEditUpdates( $revision, $wgUser );
		return true;
		*/
	}
}
