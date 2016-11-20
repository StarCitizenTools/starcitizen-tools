<?php
// https://commons.wikimedia.org/w/api.php?action=query&prop=imageinfo&iiprop=extmetadata&format=dbgfm&titles=File:Sunrise_over_fishing_boats_in_Kerala.jpg
// 'hidden' => '' fields are removed, those will be added by the API
// had to modify by hand because parsing of multiple whitespaces differs - that should be tracked down eventually
// (possibly just a version difference - the local behavior seems more correct, old values could have
// gotten cached on Commons) but it is a very minor issue

return array (
	'DateTimeOriginal' =>
		array (
			'value' => '2009-02-18',
			'source' => 'commons-desc-page',
		),
	'License' =>
		array (
			'value' => 'cc-by-sa-3.0',
			'source' => 'commons-templates',
		),
	'ImageDescription' =>
		array (
			'value' => 'Sunrise over fishing boats on the beach south of <a href="//en.wikipedia.org/wiki/Kovalam" class="extiw" title="en:Kovalam">Kovalam</a>, Kerala, South India.',
			'source' => 'commons-desc-page',
		),
	'Credit' =>
		array (
			'value' => '<span class="int-own-work">Own work</span>',
			'source' => 'commons-desc-page',
		),
	'Artist' =>
		array (
			'value' => '<a href="//commons.wikimedia.org/wiki/User:Fabrice_Florin" title="User:Fabrice Florin">User:Fabrice Florin</a>',
			'source' => 'commons-desc-page',
		),
	'LicenseShortName' =>
		array (
			'value' => 'CC-BY-SA-3.0',
			'source' => 'commons-desc-page',
		),
	'UsageTerms' =>
		array (
			'value' => 'Creative Commons Attribution-Share Alike 3.0',
			'source' => 'commons-desc-page',
		),
	'LicenseUrl' =>
		array (
			'value' => 'http://creativecommons.org/licenses/by-sa/3.0',
			'source' => 'commons-desc-page',
		),
	'Copyrighted' =>
		array (
			'value' => 'True',
			'source' => 'commons-desc-page',
		),
);
