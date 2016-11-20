<?php
// https://commons.wikimedia.org/w/api.php?action=query&prop=imageinfo&iiprop=extmetadata&format=dbgfm&titles=File:Dala_Kyrka.JPG
// 'hidden' => '' fields are removed, those will be added by the API
// had to modify by hand because parsing of multiple whitespaces differs - that should be tracked down eventually,
// but it is a very minor issue

return array (
	'DateTimeOriginal' =>
		array (
			'value' => '2013-10-27 10:27:44',
			'source' => 'commons-desc-page',
		),
	'License' =>
		array (
			'value' => 'cc-by-sa-3.0',
			'source' => 'commons-templates',
		),
	'ImageDescription' =>
		array (
			'value' => 'Dala kyrka, mot väg',
			'source' => 'commons-desc-page',
		),
	'Credit' =>
		array (
			'value' => '<span class="int-own-work">Own work</span>',
			'source' => 'commons-desc-page',
		),
	'Artist' =>
		array (
			'value' => '<a href="//commons.wikimedia.org/w/index.php?title=User:Fhille&amp;action=edit&amp;redlink=1" class="new" title="User:Fhille (page does not exist)">Fhille</a>',
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
