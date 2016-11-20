<?php

namespace CommonsMetadata;

/**
 * @covers CommonsMetadata\DomNavigator
 * @group Extensions/CommonsMetadata
 */
class DomNavigatorTest extends \MediaWikiTestCase {
	public function testHasClass() {
		$navigator = new DomNavigator( '<span class="foo"></span>' );
		$node = $navigator->getByXpath( '//body/*' );
		$this->assertTrue( $navigator->hasClass( $node, 'foo' ) );
		$this->assertFalse( $navigator->hasClass( $node, 'bar' ) );
		$this->assertFalse( $navigator->hasClass( $node, 'foo bar' ) );

		$navigator = new DomNavigator( '<span class="foo bar"></span>' );
		$node = $navigator->getByXpath( '//body/*' );
		$this->assertTrue( $navigator->hasClass( $node, 'foo' ) );
		$this->assertTrue( $navigator->hasClass( $node, 'bar' ) );
		$this->assertTrue( $navigator->hasClass( $node, 'foo bar' ) );

		$navigator = new DomNavigator( '<span class="foo bar baz boom"></span>' );
		$node = $navigator->getByXpath( '//body/*' );
		$this->assertTrue( $navigator->hasClass( $node, 'bar' ) );
		$this->assertTrue( $navigator->hasClass( $node, 'foo baz' ) );
	}

	public function testGetFirstClassWithPrefix() {
		$navigator = new DomNavigator( '<span class="foo bar baz boom"></span>' );
		$node = $navigator->getByXpath( '//body/*' );
		$this->assertEquals( 'bar', $navigator->getFirstClassWithPrefix( $node, 'ba' ) );
		$this->assertEquals( 'foo', $navigator->getFirstClassWithPrefix( $node, 'fo' ) );
		$this->assertEquals( 'boom', $navigator->getFirstClassWithPrefix( $node, 'boom' ) );
		$this->assertNull( $navigator->getFirstClassWithPrefix( $node, 'zzap' ) );
		$this->assertNull( $navigator->getFirstClassWithPrefix( $node, 'ar' ) );
	}

	public function testFindElementsWithClass() {
		// one result
		$navigator = new DomNavigator( '<div><span>1</span><span class="foo">2</span><span>3</span></div>' );
		$nodes = $navigator->findElementsWithClass( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '2' ), $nodes );

		// more results
		$navigator = new DomNavigator( '<div><span>1</span><span class="foo">2</span><span class="foo">3</span></div>' );
		$nodes = $navigator->findElementsWithClass( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '2', '3' ), $nodes );

		// multiple classes
		$navigator = new DomNavigator( '<div><span>1</span><span class="foo bar baz">2</span><span>3</span></div>' );
		$nodes = $navigator->findElementsWithClass( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '2' ), $nodes );
		$nodes = $navigator->findElementsWithClass( 'span', 'bar' );
		$this->assertNodeListTextEquals( array( '2' ), $nodes );
		$this->assertEquals( 1, $nodes->length );
		$this->assertNodeListTextEquals( array( '2' ), $nodes );

		// results nested into each other
		$navigator = new DomNavigator( '<div><span x="1"></span><span class="foo" x="2"><span class="foo" x="3"></span></span></div>' );
		$nodes = $navigator->findElementsWithClass( 'span', 'foo' );
		$this->assertNodeListAttributeEquals( 'x', array( '2', '3' ), $nodes );
	}

	public function testFindElementsWithClassPrefix() {
		// one class
		$navigator = new DomNavigator( '<div><span class="foo">1</span><span class="foobar">2</span><span class="barfoo">3</span></div>' );
		$nodes = $navigator->findElementsWithClassPrefix( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '1', '2' ), $nodes );

		// more classes
		$navigator = new DomNavigator( '<div><span class="baz foobar boom">1</span><span class="foobar baz">2</span><span class="baz foobar">3</span></div>' );
		$nodes = $navigator->findElementsWithClassPrefix( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '1', '2', '3' ), $nodes );

		// more classes - negative
		$navigator = new DomNavigator( '<div><span class="baz barfoo boom">1</span><span class="fo obar baz">2</span><span class="baz fo bar">3</span></div>' );
		$nodes = $navigator->findElementsWithClassPrefix( 'span', 'foo' );
		$this->assertNodeListTextEquals( array(), $nodes );
	}

	public function testTagNameSelector() {
		$navigator = new DomNavigator( '<div><span class="foo">1</span><div class="foo">2</div><span>3</span></div>' );
		$nodes = $navigator->findElementsWithClass( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '1' ), $nodes );

		$nodes = $navigator->findElementsWithClass( '*', 'foo' );
		$this->assertNodeListTextEquals( array( '1', '2' ), $nodes );
	}

	public function testContext() {
		$navigator = new DomNavigator( '<div><span class="foo">1</span><span class="bar"><span class="foo">2</span></span></div>' );
		$nodes = $navigator->findElementsWithClass( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '1', '2' ), $nodes );

		$nodes = $navigator->findElementsWithClass( 'span', 'bar' );
		$this->assertEquals( 1, $nodes->length );
		$context = $nodes->item( 0 );
		$nodes = $navigator->findElementsWithClass( 'span', 'foo', $context );
		$this->assertNodeListTextEquals( array( '2' ), $nodes );
	}

	public function testContextItselfIsFound() {
		$navigator = new DomNavigator( '<div><span>1</span><span class="foo">2</span><span>3</span></div>' );
		$nodes = $navigator->findElementsWithClass( 'span', 'foo' );
		$context = $nodes->item( 0 );
		$nodes = $navigator->findElementsWithClass( 'span', 'foo', $context );
		$this->assertNodeListTextEquals( array( '2' ), $nodes );
	}

	public function testMultipleElementNames() {
		$navigator = new DomNavigator( '<div><span class="foo">1</span><div class="foo">2</div><span>3</span><p class="foo">4</p></p></div>' );
		$nodes = $navigator->findElementsWithClass( '*', 'foo' );
		$this->assertNodeListTextEquals( array( '1', '2', '4' ), $nodes );

		$nodes = $navigator->findElementsWithClass( array( 'span', 'div' ), 'foo' );
		$this->assertNodeListTextEquals( array( '1', '2' ), $nodes );
	}

	public function testFindElementsWithClassAndLang() {
		$navigator = new DomNavigator( '<div><span lang="en">1</span><span class="foo">2</span><span lang="en" class="foo">3</span></div>' );
		$nodes = $navigator->findElementsWithClassAndLang( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '3' ), $nodes );

		$navigator = new DomNavigator( '<div><span lang="en" class="foo">1</span><span lang="de" class="foo">2</span></div>' );
		$nodes = $navigator->findElementsWithClassAndLang( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '1', '2' ), $nodes );
	}

	public function testFindElementsWithId() {
		// test multiple identical ids in same document
		$navigator = new DomNavigator( '<div><span>1</span><span id="foo">2</span><span id="foo">3</span></div>' );
		$nodes = $navigator->findElementsWithId( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '2', '3' ), $nodes );
	}

	public function testFindElementsWithIdPrefix() {
		$navigator = new DomNavigator( '<div><span id="foo">1</span><span id="foobar">2</span><span id="barfoo">3</span></div><span>4</span>' );
		$nodes = $navigator->findElementsWithIdPrefix( 'span', 'foo' );
		$this->assertNodeListTextEquals( array( '1', '2' ), $nodes );
	}

	public function testFindElementsWithAttribute() {
		$navigator = new DomNavigator( '<span class="foo">1</span><span class>2</span><span id="bar">3</span>' );
		$nodes = $navigator->findElementsWithAttribute( 'span', 'class' );
		$this->assertNodeListTextEquals( array( '1', '2' ), $nodes );
	}

	public function testClosest() {
		$navigator = new DomNavigator( '<span><ul id="a"><li id="b"><span id="c"><b></b></span></li></ul></span>' );
		$node = $navigator->getByXpath( "//*[@id = 'c']" );

		$closest = $navigator->closest( $node, 'ul' );
		$this->assertInstanceOf( 'DOMElement', $closest );
		$this->assertEquals( 'a', $closest->getAttribute( 'id' ) );

		$closest = $navigator->closest( $node, 'li' );
		$this->assertInstanceOf( 'DOMElement', $closest );
		$this->assertEquals( 'b', $closest->getAttribute( 'id' ) );

		$closest = $navigator->closest( $node, 'span' );
		$this->assertInstanceOf( 'DOMElement', $closest );
		$this->assertEquals( 'c', $closest->getAttribute( 'id' ) );

		$closest = $navigator->closest( $node, 'b' );
		$this->assertNull( $closest );
	}

	public function testNextSibling() {
		$navigator = new DomNavigator( '<div><span>1</span><span id="foo">2</span><span>3</span><span>4</span></div>' );
		$node = $navigator->getByXpath( "//*[@id = 'foo']" );
		$nextSibling = $navigator->nextElementSibling( $node );
		$this->assertInstanceOf( 'DOMElement', $nextSibling );
		$this->assertEquals( 3, $nextSibling->textContent );

		$navigator = new DomNavigator( '<div><span>1</span><span id="foo">2</span>asd<!--fgh--><span>3</span></div>' );
		$node = $navigator->getByXpath( "//*[@id = 'foo']" );
		$nextSibling = $navigator->nextElementSibling( $node );
		$this->assertInstanceOf( 'DOMElement', $nextSibling );
		$this->assertEquals( 3, $nextSibling->textContent );

		$navigator = new DomNavigator( '<div><span>1</span><span id="foo">2</span>' );
		$node = $navigator->getByXpath( "//*[@id = 'foo']" );
		$nextSibling = $navigator->nextElementSibling( $node );
		$this->assertNull( $nextSibling );
	}

	/**
	 * Asserts the text of nodes in a result set agains strings in an array.
	 * @param array $expected
	 * @param \DomNodeList $nodes
	 * @param string $message
	 */
	protected function assertNodeListTextEquals( array $expected, \DOMNodeList $nodes, $message = '' ) {
		$this->assertEquals( count( $expected ) , $nodes->length );
		foreach ( $expected as $i => $text ) {
			$this->assertEquals( $text, $nodes->item( $i )->textContent, $message ?: "Failed to assert that text of node $i equals '$text'" );
		}
	}

	/**
	 * Asserts a given attribute of nodes in a result set agains strings in an array.
	 * @param string $attributeName
	 * @param array $expected
	 * @param \DomNodeList $nodes
	 * @param string $message
	 */
	protected function assertNodeListAttributeEquals( $attributeName, array $expected, \DOMNodeList $nodes, $message = '' ) {
		$this->assertEquals( count( $expected ) , $nodes->length );
		foreach ( $expected as $i => $attr ) {
			$node = $nodes->item( $i );
			$this->assertInstanceOf( 'DOMElement', $node );
			$this->assertEquals( $attr, $node->getAttribute( $attributeName ), $message ?: "Failed to assert that "
				. "attribute '$attributeName' of node $i equals '$attr'" );
		}
	}
}
