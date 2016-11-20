<?php

namespace CommonsMetadata;

use DOMDocument;
use DOMXPath;
use DOMNode;
use DOMElement;
use DOMNodeList;

/**
 * A very simple wrapper to DOMDocument to make it easy to traverse nodes which match simple CSS selectors.
 */
class DomNavigator {
	/**
	 * The document to search through.
	 * @var DOMXPath
	 */
	protected $domx;

	/**
	 * @param string $html
	 */
	public function __construct( $html ) {
		// libxml mutilates UTF-8 chars unless they are encoded as entities
		$html = mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );

		$oldLoaderState = libxml_disable_entity_loader( true );
		$oldHandlerState = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( $html );
		$this->domx = new DOMXPath( $dom );
		libxml_disable_entity_loader( $oldLoaderState );
		libxml_use_internal_errors( $oldHandlerState );
	}

	/**
	 * Returns a list of elements of the given type which have the given class.
	 * (In other words, this is equivalent to the CSS selector 'element.class'.)
	 * @param string|array $element HTML tag name (* to accept all) or array of tag names
	 * @param string $class
	 * @param DOMNode $context if present, the method will only search inside this element
	 * @return DOMNodeList|DOMElement[]
	 */
	public function findElementsWithClass( $element, $class, DOMNode $context = null ) {
		$element = $this->handleElementOrList( $element );
		$xpath = "./descendant-or-self::{$element}[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]";
		return $this->findByXpath( $xpath, $context );
	}

	/**
	 * Returns a list of elements of the given type which have a class starting with the given string.
	 * @param string|array $element HTML tag name (* to accept all) or array of tag names
	 * @param string $classPrefix
	 * @param DOMNode $context if present, the method will only search inside this element
	 * @return DOMNodeList|DOMElement[]
	 */
	public function findElementsWithClassPrefix( $element, $classPrefix, DOMNode $context = null ) {
		$element = $this->handleElementOrList( $element );
		$xpath = "./descendant-or-self::{$element}[contains(concat(' ', normalize-space(@class)), ' $classPrefix')]";
		return $this->findByXpath( $xpath, $context );
	}

	/**
	 * Returns a list of elements of the given type which have the given class and any lang attribute.
	 * (In other words, this is equivalent to the CSS selector 'element.class[lang]'.)
	 * @param string|array $element HTML tag name (* to accept all) or array of tag names
	 * @param string $class
	 * @param DOMNode $context if present, the method will only search inside this element
	 * @return DOMNodeList|DOMElement[]
	 */
	public function findElementsWithClassAndLang( $element, $class, DOMNode $context = null ) {
		$element = $this->handleElementOrList( $element );
		$xpath = "./descendant-or-self::{$element}[@lang and contains(concat(' ', normalize-space(@class), ' '), ' $class ')]";
		return $this->findByXpath( $xpath, $context );
	}

	/**
	 * Returns a list of elements of the given type which have the given id.
	 * (In other words, this is equivalent to the CSS selector 'element#id'.)
	 * When there are multiple elements with this ID, all are returned.
	 * @param string|array $element HTML tag name (* to accept all) or array of tag names
	 * @param string $id
	 * @param DOMNode $context if present, the method will only search inside this element
	 * @return DOMNodeList|DOMElement[]
	 */
	public function findElementsWithId( $element, $id, DOMNode $context = null ) {
		$element = $this->handleElementOrList( $element );
		$xpath = "./descendant-or-self::{$element}[@id='$id']";
		return $this->findByXpath( $xpath, $context );
	}

	/**
	 * Returns a list of elements of the given type which have an id starting with the given prefix.
	 * (In other words, this is equivalent to the CSS selector 'element[id^=prefix]'.)
	 * @param string|array $element HTML tag name (* to accept all) or array of tag names
	 * @param string $idPrefix
	 * @param DOMNode $context if present, the method will only search inside this element
	 * @return DOMNodeList|DOMElement[]
	 */
	public function findElementsWithIdPrefix( $element, $idPrefix, DOMNode $context = null ) {
		$element = $this->handleElementOrList( $element );
		$xpath = "./descendant-or-self::{$element}[starts-with(@id, '$idPrefix')]";
		return $this->findByXpath( $xpath, $context );
	}

	/**
	 * Returns a list of elements of the given type which have the given attribute with any value.
	 * (In other words, this is equivalent to the CSS selector 'element[attribute]'.)
	 * When there are multiple elements with this attribute, all are returned.
	 * @param string|array $element HTML tag name (* to accept all) or array of tag names
	 * @param string $attribute
	 * @param DOMNode $context if present, the method will only search inside this element
	 * @return DOMNodeList|DOMElement[]
	 */
	public function findElementsWithAttribute( $element, $attribute, DOMNode $context = null ) {
		$element = $this->handleElementOrList( $element );
		$xpath = "./descendant-or-self::{$element}[@{$attribute}]";
		return $this->findByXpath( $xpath, $context );
	}

	/**
	 * Returns true if the node has all the specified classes.
	 * @param DOMNode $node
	 * @param string $classes one or more class names (separated with space)
	 * @return bool
	 */
	public function hasClass( DOMNode $node, $classes ) {
		if ( ! $node instanceof \DOMElement ) {
			return false;
		}
		$nodeClasses = explode( ' ', $node->getAttribute( 'class' ) );
		$testClasses = explode( ' ', $classes );
		return !array_diff( $testClasses, $nodeClasses );
	}

	/**
	 * Returns the first class matching a prefix.
	 * @param DOMNode $node
	 * @param string $classPrefix
	 * @return string|null
	 */
	public function getFirstClassWithPrefix( DOMNode $node, $classPrefix ) {
		if ( ! $node instanceof \DOMElement ) {
			return null;
		}
		$classes = explode( ' ', $node->getAttribute( 'class' ) );
		foreach ( $classes as $class ) {
			$length = strlen( $classPrefix );
			if ( substr( $class, 0, $length ) === $classPrefix ) {
				return $class;
			}
		}
		return null;
	}

	/**
	 * Returns the closest ancestor of the given node, which is of the given type (like jQuery.closest())
	 * @param DOMNode $node
	 * @param string $element HTML tag name
	 * @return DOMElement|null
	 */
	public function closest( DOMNode $node, $element ) {
		while ( ! $node instanceof DOMElement || $node->nodeName !== $element ) {
			if ( $node->parentNode instanceof DOMNode ) {
				$node = $node->parentNode;
			} else {
				return null;
			}
		}
		return  $node;
	}

	/**
	 * Returns the nodes matching an XPath expression.
	 * @param string $xpath
	 * @param DOMNode $context
	 * @return DOMNodeList|DOMNode[]
	 */
	public function findByXpath( $xpath, DOMNode $context = null ) {
		$results = $this->domx->query( $xpath, $context );
		if ( $results === false ) {
			$error = libxml_get_last_error();
			$logMessage = sprintf( 'HTML parsing error: %s (%s) at line %s, columnt %s',
				$error->message, $error->code, $error->line, $error->column);
			wfDebugLog( 'CommonsMetadata', $logMessage );
			return new DOMNodeList();
		}
		return $results;
	}

	/**
	 * Returns the first node matching an XPath expression, or null.
	 * @param string $xpath
	 * @param DOMNode $context
	 * @return DOMNode|null
	 */
	public function getByXpath( $xpath, DOMNode $context = null ) {
		$results = $this->findByXpath( $xpath, $context );
		foreach ( $results as $result ) {
			return $result;
		}
		return null;
	}

	/**
	 * Return next sibling element (or null)
	 * @param DOMElement $node
	 * @return DOMElement|null
	 */
	public function nextElementSibling( DOMElement $node ) {
		$nextSibling = $node->nextSibling;
		while ( $nextSibling && ! $nextSibling instanceof DOMElement ) {
			$nextSibling = $nextSibling->nextSibling;
		}
		return $nextSibling;
	}

	/**
	 * Takes an element name or array of element names and returns an XPath expression which can
	 * be used as an element name, but matches all of the provided elements.
	 * @param string|array $elmementOrList
	 * @return string
	 */
	protected function handleElementOrList( $elmementOrList ) {
		if ( is_array( $elmementOrList ) ) {
			return '*[' . implode( ' or ', array_map( function ( $el ) { return 'self::' . $el; }, $elmementOrList ) ) . ']';
		} else {
			return $elmementOrList;
		}
	}

}
