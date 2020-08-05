<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\WikiSEO;

/**
 * Class Validator
 *
 * @package MediaWiki\Extension\WikiSEO
 */
class Validator {
	public static $validParams = [
	'title',
	'title_mode',
	'title_separator',

	'keywords',
	'description',

	'robots',
	'google_bot',

	'image', 'image_width', 'image_height', 'image_alt',

	'type',
	'site_name',
	'locale',
	'section',
	'author',

	'published_time',

	'twitter_site',
	'hreflang_%'
	];

	/**
	 * https://stackoverflow.com/a/3191729
	 *
	 * @var array Language codes
	 */
	public static $isoLanguageCodes = [
	'af-za',
	'am-et',
	'ar-ae',
	'ar-bh',
	'ar-dz',
	'ar-eg',
	'ar-iq',
	'ar-jo',
	'ar-kw',
	'ar-lb',
	'ar-ly',
	'ar-ma',
	'arn-cl',
	'ar-om',
	'ar-qa',
	'ar-sa',
	'ar-sy',
	'ar-tn',
	'ar-ye',
	'as-in',
	'az-cyrl-az',
	'az-latn-az',
	'ba-ru',
	'be-by',
	'bg-bg',
	'bn-bd',
	'bn-in',
	'bo-cn',
	'br-fr',
	'bs-cyrl-ba',
	'bs-latn-ba',
	'ca-es',
	'co-fr',
	'cs-cz',
	'cy-gb',
	'da-dk',
	'de-at',
	'de-ch',
	'de-de',
	'de-li',
	'de-lu',
	'dsb-de',
	'dv-mv',
	'el-gr',
	'en-029',
	'en-au',
	'en-bz',
	'en-ca',
	'en-gb',
	'en-ie',
	'en-in',
	'en-jm',
	'en-my',
	'en-nz',
	'en-ph',
	'en-sg',
	'en-tt',
	'en-us',
	'en-za',
	'en-zw',
	'es-ar',
	'es-bo',
	'es-cl',
	'es-co',
	'es-cr',
	'es-do',
	'es-ec',
	'es-es',
	'es-gt',
	'es-hn',
	'es-mx',
	'es-ni',
	'es-pa',
	'es-pe',
	'es-pr',
	'es-py',
	'es-sv',
	'es-us',
	'es-uy',
	'es-ve',
	'et-ee',
	'eu-es',
	'fa-ir',
	'fi-fi',
	'fil-ph',
	'fo-fo',
	'fr-be',
	'fr-ca',
	'fr-ch',
	'fr-fr',
	'fr-lu',
	'fr-mc',
	'fy-nl',
	'ga-ie',
	'gd-gb',
	'gl-es',
	'gsw-fr',
	'gu-in',
	'ha-latn-ng',
	'he-il',
	'hi-in',
	'hr-ba',
	'hr-hr',
	'hsb-de',
	'hu-hu',
	'hy-am',
	'id-id',
	'ig-ng',
	'ii-cn',
	'is-is',
	'it-ch',
	'it-it',
	'iu-cans-ca',
	'iu-latn-ca',
	'ja-jp',
	'ka-ge',
	'kk-kz',
	'kl-gl',
	'km-kh',
	'kn-in',
	'kok-in',
	'ko-kr',
	'ky-kg',
	'lb-lu',
	'lo-la',
	'lt-lt',
	'lv-lv',
	'mi-nz',
	'mk-mk',
	'ml-in',
	'mn-mn',
	'mn-mong-cn',
	'moh-ca',
	'mr-in',
	'ms-bn',
	'ms-my',
	'mt-mt',
	'nb-no',
	'ne-np',
	'nl-be',
	'nl-nl',
	'nn-no',
	'nso-za',
	'oc-fr',
	'or-in',
	'pa-in',
	'pl-pl',
	'prs-af',
	'ps-af',
	'pt-br',
	'pt-pt',
	'qut-gt',
	'quz-bo',
	'quz-ec',
	'quz-pe',
	'rm-ch',
	'ro-ro',
	'ru-ru',
	'rw-rw',
	'sah-ru',
	'sa-in',
	'se-fi',
	'se-no',
	'se-se',
	'si-lk',
	'sk-sk',
	'sl-si',
	'sma-no',
	'sma-se',
	'smj-no',
	'smj-se',
	'smn-fi',
	'sms-fi',
	'sq-al',
	'sr-cyrl-ba',
	'sr-cyrl-cs',
	'sr-cyrl-me',
	'sr-cyrl-rs',
	'sr-latn-ba',
	'sr-latn-cs',
	'sr-latn-me',
	'sr-latn-rs',
	'sv-fi',
	'sv-se',
	'sw-ke',
	'syr-sy',
	'ta-in',
	'te-in',
	'tg-cyrl-tj',
	'th-th',
	'tk-tm',
	'tn-za',
	'tr-tr',
	'tt-ru',
	'tzm-latn-dz',
	'ug-cn',
	'uk-ua',
	'ur-pk',
	'uz-cyrl-uz',
	'uz-latn-uz',
	'vi-vn',
	'wo-sn',
	'xh-za',
	'yo-ng',
	'zh-cn',
	'zh-hk',
	'zh-mo',
	'zh-sg',
	'zh-tw',
	'zu-za',
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
			$valid =
			in_array( $paramKey, static::$validParams, true ) ||
			in_array( substr( $paramKey, 9 ), static::$isoLanguageCodes, true );

			if ( $valid ) {
				$validatedParams[$paramKey] = $paramData;
			}
		}

		return $validatedParams;
	}
}
