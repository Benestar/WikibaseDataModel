<?php

namespace Wikibase\Test;

use Wikibase\Claim;
use Wikibase\ClaimAggregate;

/**
 * Tests for the Wikibase\ClaimAggregate implementing classes.
 *
 * @file
 * @since 0.2
 *
 * @ingroup WikibaseLib
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseDataModel
 * @group WikibaseClaim
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ClaimAggregateTest extends \PHPUnit_Framework_TestCase {

	public function ClaimTestProvider() {
		$claims = array();

		$claims[] = new \Wikibase\Claim( new \Wikibase\PropertyNoValueSnak(
			new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 42 )
		) );
		$claims[] = new \Wikibase\Claim( new \Wikibase\PropertyValueSnak(
			new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 23 ),
			new \DataValues\StringValue( 'ohi' )
		) );

		$aggregates = array();

		$aggregates[] = \Wikibase\Property::newEmpty();

		// TODO
		// $aggregates[] = \Wikibase\Query::newEmpty();

		$argLists = array();

		/**
		 * @var ClaimAggregate $aggregate
		 */
		foreach ( $aggregates as $aggregate ) {
			foreach ( $claims as $claim ) {
				$argLists[] = array( clone $aggregate, array( $claim ) );
			}

			$argLists[] = array( clone $aggregate, $claims );
		}

		return $argLists;
	}

	/**
	 * @dataProvider ClaimTestProvider
	 *
	 * @param ClaimAggregate $aggregate
	 * @param array $claims
	 */
	public function testAllOfTheStuff( ClaimAggregate $aggregate, array $claims ) {
		$obtainedClaims = new \Wikibase\Claims( $aggregate->getClaims() );
		$this->assertInstanceOf( '\Wikibase\Claims', $obtainedClaims );

		// Below code tests if the Claims in the ClaimAggregate indeed do not get modified.

		$unmodifiedClaims = clone $obtainedClaims;

		$qualifiers = new \Wikibase\SnakList( array( new \Wikibase\PropertyValueSnak(
			new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 10 ),
			new \DataValues\StringValue( 'ohi' )
		) ) );

		/**
		 * @var Claim $claim
		 */
		foreach ( $obtainedClaims as $claim ) {
			$claim->setQualifiers( $qualifiers );
		}

		foreach ( $claims as $claim ) {
			$obtainedClaims->addClaim( $claim );
		}

		$freshlyObtained = new \Wikibase\Claims( $aggregate->getClaims() );

		$this->assertEquals(
			iterator_to_array( $unmodifiedClaims ),
			iterator_to_array( $freshlyObtained ),
			'Was able to modify statements via ClaimAggregate::getClaims'
		);

		foreach ( $freshlyObtained as $obtainedClaim ) {
			$this->assertTrue( $unmodifiedClaims->hasClaim( $obtainedClaim ) );
		}
	}

}
