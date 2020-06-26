<?php

use MediaWiki\Logger\LoggerFactory;

class EDHttpWithHeaders extends Http {
	/**
	 * @see Http::request()
	 * Only diffrence - $options variable an also have value 'headers', and would append to request before sending
	 */
	public static function request( $method, $url, $options = [], $caller = __METHOD__ ) {
		wfDebug( "HTTP: $method: $url\n" );

		$options['method'] = strtoupper( $method );

		if ( !isset( $options['timeout'] ) ) {
			$options['timeout'] = 'default';
		}
		if ( !isset( $options['connectTimeout'] ) ) {
			$options['connectTimeout'] = 'default';
		}

		$req = MWHttpRequest::factory( $url, $options, $caller );
		if ( isset( $options['headers'] ) ) {
			foreach ( $options['headers'] as $headerName => $headerValue ) {
				$req->setHeader( $headerName, $headerValue );
			}
		}
		$status = $req->execute();

		if ( $status->isOK() ) {
			return $req->getContent();
		} else {
			$errors = $status->getErrorsByType( 'error' );
			$logger = LoggerFactory::getInstance( 'http' );
			$logger->warning( Status::wrap( $status )->getWikiText( false, false, 'en' ),
				[ 'error' => $errors, 'caller' => $caller, 'content' => $req->getContent() ] );
			return false;
		}
	}

	/**
	 * Simple wrapper for Http::request( 'POST' )
	 * this is copy of Http::post, the only reason to redeclare it is becouse Http calls Http::request instead of self::request
	 * @see Http::request()
	 *
	 * @param string $url
	 * @param array $options
	 * @param string $caller The method making this request, for profiling
	 * @return string|bool false on error
	 */
	public static function post( $url, $options = [], $caller = __METHOD__ ) {
		return self::request( 'POST', $url, $options, $caller );
	}

	/**
	 * Simple wrapper for Http::request( 'GET' )
	 * this is copy of Http::get, the only reason to redeclare it is becouse Http calls Http::request instead of self::request
	 * @see Http::request()
	 * @since 1.25 Second parameter $timeout removed. Second parameter
	 * is now $options which can be given a 'timeout'
	 *
	 * @param string $url
	 * @param array $options
	 * @param string $caller The method making this request, for profiling
	 * @return string|bool false on error
	 */
	public static function get( $url, $options = [], $caller = __METHOD__ ) {
		$args = func_get_args();
		if ( isset( $args[1] ) && ( is_string( $args[1] ) || is_numeric( $args[1] ) ) ) {
			// Second was used to be the timeout
			// And third parameter used to be $options
			wfWarn( "Second parameter should not be a timeout.", 2 );
			$options = isset( $args[2] ) && is_array( $args[2] ) ?
				$args[2] : [];
			$options['timeout'] = $args[1];
			$caller = __METHOD__;
		}
		return self::request( 'GET', $url, $options, $caller );
	}
}
