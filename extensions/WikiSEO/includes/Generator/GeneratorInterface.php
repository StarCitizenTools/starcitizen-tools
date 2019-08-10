<?php

namespace Octfx\WikiSEO\Generator;

use OutputPage;

/**
 * Interface for metadata generators
 *
 * @package Octfx\WikiSEO\Generator
 */
interface GeneratorInterface
{
	/**
	 * Initialize the generator with all metadata and the page to output the metadata onto
	 *
	 * @param array $metadata All metadata
	 * @param OutputPage $out The page to add the metadata to
	 *
	 * @return void
	 */
	public function init( array $metadata, OutputPage $out );

	/**
	 * Add the metadata to the OutputPage
	 *
	 * @return void
	 */
	public function addMetadata();
}