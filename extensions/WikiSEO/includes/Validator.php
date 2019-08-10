<?php

namespace Octfx\WikiSEO;

/**
 * Class Validator
 *
 * @package Octfx\WikiSEO
 */
class Validator {
	private static $validParams = [
		'title',
		'title_mode',
		'title_separator',

		'keywords',
		'description',

		'robots',

		'image', 'image_width', 'image_height',

		'type',
		'site_name',
		'locale',
		'section',
		'author',

		'published_time', 'modified_time',

		'twitter_site',
	];

	/**
	 * Removes all params that are not in §valid_params
	 *
	 * @param array $params Raw params
	 * @return array Validated params
	 */
	public function validateParams( array $params ) {
		$validatedParams = [];

		foreach ( $params as $paramKey => $paramData ) {
			if ( in_array( $paramKey, self::$validParams, true ) ) {
				$validatedParams[$paramKey] = $paramData;
			}
		}

		return $validatedParams;
	}
}