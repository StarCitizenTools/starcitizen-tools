<?php

/**
 * Hook functions for the External Data extension.
 *
 * @file
 * @ingroup ExternalData
 * @author Yaron Koren
 */
class ExternalDataHooks {

	/**
	 * @param Parser $parser
	 * @return bool
	 */
	public static function registerParser( Parser $parser ) {
		$parser->setFunctionHook( 'get_web_data', [ 'EDParserFunctions', 'doGetWebData' ] );
		$parser->setFunctionHook( 'get_file_data', [ 'EDParserFunctions', 'doGetFileData' ] );
		$parser->setFunctionHook( 'get_soap_data', [ 'EDParserFunctions', 'doGetSOAPData' ] );
		$parser->setFunctionHook( 'get_ldap_data', [ 'EDParserFunctions', 'doGetLDAPData' ] );
		$parser->setFunctionHook( 'get_db_data', [ 'EDParserFunctions', 'doGetDBData' ] );
		$parser->setFunctionHook( 'external_value', [ 'EDParserFunctions', 'doExternalValue' ] );
		$parser->setFunctionHook( 'for_external_table', [ 'EDParserFunctions', 'doForExternalTable' ] );
		$parser->setFunctionHook( 'display_external_table', [ 'EDParserFunctions', 'doDisplayExternalTable' ] );
		$parser->setFunctionHook( 'store_external_table', [ 'EDParserFunctions', 'doStoreExternalTable' ] );
		$parser->setFunctionHook( 'clear_external_data', [ 'EDParserFunctions', 'doClearExternalData' ] );

		return true; // always return true, in order not to stop MW's hook processing!
	}

}
