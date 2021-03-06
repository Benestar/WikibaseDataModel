<?php

namespace Wikibase\DataModel;

use Comparable;
use InvalidArgumentException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdSet;

/**
 * Immutable value object representing a link to a page on another site.
 *
 * A set of badges, represented as ItemId objects, acts as flags
 * describing attributes of the linked to page.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Michał Łazowik
 */
class SiteLink implements Comparable {

	protected $siteId;
	protected $pageName;

	/**
	 * @var ItemIdSet
	 */
	protected $badges;

	/**
	 * @param string $siteId
	 * @param string $pageName
	 * @param ItemIdSet|ItemId[] $badges
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $siteId, $pageName, $badges = array() ) {
		if ( !is_string( $siteId ) ) {
			throw new InvalidArgumentException( '$siteId needs to be a string' );
		}

		if ( !is_string( $pageName ) ) {
			throw new InvalidArgumentException( '$pageName needs to be a string' );
		}

		$this->siteId = $siteId;
		$this->pageName = $pageName;
		$this->setBadges( $badges );
	}

	private function setBadges( $badges ) {
		if ( is_array( $badges ) ) {
			$badges = new ItemIdSet( $badges );
		}
		elseif ( !( $badges instanceof ItemIdSet ) ) {
			throw new InvalidArgumentException( '$badges needs to be ItemIdSet or ItemId[]' );
		}

		$this->badges = $badges;
	}

	/**
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getSiteId() {
		return $this->siteId;
	}

	/**
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getPageName() {
		return $this->pageName;
	}

	/**
	 * Badges are not order dependent.
	 *
	 * @since 0.5
	 *
	 * @return ItemId[]
	 */
	public function getBadges() {
		return array_values( iterator_to_array( $this->badges ) );
	}

	/**
	 * @see Comparable::equals
	 *
	 * @since 0.7.4
	 *
	 * @param mixed $target
	 *
	 * @return boolean
	 */
	public function equals( $target ) {
		if ( !( $target instanceof self ) ) {
			return false;
		}

		return $this->siteId === $target->siteId
			&& $this->pageName === $target->pageName
			&& $this->badges->equals( $target->badges );
	}

}
