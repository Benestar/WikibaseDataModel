<?php

namespace Wikibase\DataModel\Entity;

use DataValues\Serializers\DataValueSerializer;
use Diff\Differ\Differ;
use Diff\Differ\MapDiffer;
use Diff\Patcher\MapPatcher;
use InvalidArgumentException;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Claim\ClaimAggregate;
use Wikibase\DataModel\Claim\Claims;
use Wikibase\DataModel\Internal\LegacyIdInterpreter;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Term\AliasGroup;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\InternalSerialization\SerializerFactory;

/**
 * Represents a single Wikibase entity.
 * See https://meta.wikimedia.org/wiki/Wikidata/Data_model#Values
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class Entity implements \Comparable, ClaimAggregate, FingerprintProvider {

	/**
	 * @var EntityId|null
	 */
	protected $id;

	/**
	 * @var Fingerprint
	 */
	protected $fingerprint;

	/**
	 * Returns the id of the entity or null if it does not have one.
	 *
	 * @since 0.1 return type changed in 0.3
	 *
	 * @return EntityId|null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Can be EntityId since 0.3.
	 * The support for setting an integer here is deprecated since 0.5.
	 * New deriving classes are allowed to reject anything that is not an EntityId of the correct type.
	 * Null can be provided as of 1.0.
	 *
	 * @since 0.1
	 *
	 * @param EntityId|null $id
	 *
	 * @throws InvalidArgumentException
	 */
	public function setId( $id ) {
		if ( $id === null ) {
			$this->id = null;
		}
		else if ( $id instanceof EntityId ) {
			if ( $id->getEntityType() !== $this->getType() ) {
				throw new InvalidArgumentException( 'Attempt to set an EntityId with mismatching entity type' );
			}

			// This ensures the id is an instance of the correct derivative of EntityId.
			// EntityId (non-derivative) instances are thus converted.
			$this->id = $this->idFromSerialization( $id->getSerialization() );
		}
		else if ( is_integer( $id ) ) {
			$this->id = LegacyIdInterpreter::newIdFromTypeAndNumber( $this->getType(), $id );
		}
		else {
			throw new InvalidArgumentException( __METHOD__ . ' only accepts EntityId and integer' );
		}
	}

	/**
	 * Sets the value for the label in a certain value.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 * @param string $value
	 *
	 * @return string
	 */
	public function setLabel( $languageCode, $value ) {
		$this->fingerprint->getLabels()->setTerm( new Term( $languageCode, $value ) );
		return $value;
	}

	/**
	 * Sets the value for the description in a certain value.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 * @param string $value
	 *
	 * @return string
	 */
	public function setDescription( $languageCode, $value ) {
		$this->fingerprint->getDescriptions()->setTerm( new Term( $languageCode, $value ) );
		return $value;
	}

	/**
	 * Removes the labels in the specified languages.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 */
	public function removeLabel( $languageCode ) {
		$this->fingerprint->getLabels()->removeByLanguage( $languageCode );
	}

	/**
	 * Removes the descriptions in the specified languages.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 */
	public function removeDescription( $languageCode ) {
		$this->fingerprint->getDescriptions()->removeByLanguage( $languageCode );
	}

	/**
	 * Returns the aliases for the item in the language with the specified code.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 *
	 * @return string[]
	 */
	public function getAliases( $languageCode ) {
		$aliases = $this->fingerprint->getAliasGroups();

		if ( $aliases->hasGroupForLanguage( $languageCode ) ) {
			return $aliases->getByLanguage( $languageCode )->getAliases();
		}

		return array();
	}

	/**
	 * Returns all the aliases for the item.
	 * The result is an array with language codes pointing to an array of aliases in the language they specify.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string[]|null $languageCodes
	 *
	 * @return array[]
	 */
	public function getAllAliases( array $languageCodes = null ) {
		$aliases = $this->fingerprint->getAliasGroups();

		$textLists = array();

		/**
		 * @var AliasGroup $aliasGroup
		 */
		foreach ( $aliases as $languageCode => $aliasGroup ) {
			if ( $languageCodes === null || in_array( $languageCode, $languageCodes ) ) {
				$textLists[$languageCode] = $aliasGroup->getAliases();
			}
		}

		return $textLists;
	}

	/**
	 * Sets the aliases for the item in the language with the specified code.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 * @param string[] $aliases
	 */
	public function setAliases( $languageCode, array $aliases ) {
		$this->fingerprint->getAliasGroups()->setGroup( new AliasGroup( $languageCode, $aliases ) );
	}

	/**
	 * Add the provided aliases to the aliases list of the item in the language with the specified code.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 * @param string[] $aliases
	 */
	public function addAliases( $languageCode, array $aliases ) {
		$this->setAliases(
			$languageCode,
			array_merge(
				$this->getAliases( $languageCode ),
				$aliases
			)
		);
	}

	/**
	 * Removed the provided aliases from the aliases list of the item in the language with the specified code.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 * @param string[] $aliases
	 */
	public function removeAliases( $languageCode, array $aliases ) {
		$this->setAliases(
			$languageCode,
			array_diff(
				$this->getAliases( $languageCode ),
				$aliases
			)
		);
	}

	/**
	 * Returns the descriptions of the entity in the provided languages.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string[]|null $languageCodes Note that an empty array gives descriptions for no languages while a null pointer gives all
	 *
	 * @return string[] Found descriptions in given languages
	 */
	public function getDescriptions( array $languageCodes = null ) {
		return $this->getMultilangTexts( 'description', $languageCodes );
	}

	/**
	 * Returns the labels of the entity in the provided languages.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string[]|null $languageCodes Note that an empty array gives labels for no languages while a null pointer gives all
	 *
	 * @return string[] Found labels in given languages
	 */
	public function getLabels( array $languageCodes = null ) {
		return $this->getMultilangTexts( 'label', $languageCodes );
	}

	/**
	 * Returns the description of the entity in the language with the provided code,
	 * or false in cases there is none in this language.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 *
	 * @return string|bool
	 */
	public function getDescription( $languageCode ) {
		if ( !$this->fingerprint->getDescriptions()->hasTermForLanguage( $languageCode ) ) {
			return false;
		}

		return $this->fingerprint->getDescriptions()->getByLanguage( $languageCode )->getText();
	}

	/**
	 * Returns the label of the entity in the language with the provided code,
	 * or false in cases there is none in this language.
	 *
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string $languageCode
	 *
	 * @return string|bool
	 */
	public function getLabel( $languageCode ) {
		if ( !$this->fingerprint->getLabels()->hasTermForLanguage( $languageCode ) ) {
			return false;
		}

		return $this->fingerprint->getLabels()->getByLanguage( $languageCode )->getText();
	}

	/**
	 * Get texts from an item with a field specifier.
	 *
	 * @since 0.1
	 * @deprecated
	 *
	 * @param string $fieldKey
	 * @param string[]|null $languageCodes
	 *
	 * @return string[]
	 */
	private function getMultilangTexts( $fieldKey, array $languageCodes = null ) {
		if ( $fieldKey === 'label' ) {
			$textList = $this->fingerprint->getLabels()->toTextArray();
		}
		else {
			$textList = $this->fingerprint->getDescriptions()->toTextArray();
		}

		if ( !is_null( $languageCodes ) ) {
			$textList = array_intersect_key( $textList, array_flip( $languageCodes ) );
		}

		return $textList;
	}

	/**
	 * Replaces the currently set labels with the provided ones.
	 * The labels are provided as an associative array where the keys are
	 * language codes pointing to the label in that language.
	 *
	 * @since 0.4
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string[] $labels
	 */
	public function setLabels( array $labels ) {
		$this->fingerprint->setLabels( new TermList( array() ) );

		foreach ( $labels as $languageCode => $labelText ) {
			$this->setLabel( $languageCode, $labelText );
		}
	}

	/**
	 * Replaces the currently set descriptions with the provided ones.
	 * The descriptions are provided as an associative array where the keys are
	 * language codes pointing to the description in that language.
	 *
	 * @since 0.4
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param string[] $descriptions
	 */
	public function setDescriptions( array $descriptions ) {
		$this->fingerprint->setDescriptions( new TermList( array() ) );

		foreach ( $descriptions as $languageCode => $descriptionText ) {
			$this->setDescription( $languageCode, $descriptionText );
		}
	}

	/**
	 * Replaces the currently set aliases with the provided ones.
	 * The aliases are provided as an associative array where the keys are
	 * language codes pointing to an array value that holds the aliases
	 * in that language.
	 *
	 * @since 0.4
	 * @deprecated since 0.7.3 - use getFingerprint and setFingerprint
	 *
	 * @param array[] $aliasLists
	 */
	public function setAllAliases( array $aliasLists ) {
		$this->fingerprint->setAliasGroups( new AliasGroupList( array() ) );

		foreach( $aliasLists as $languageCode => $aliasList ) {
			$this->setAliases( $languageCode, $aliasList );
		}
	}

	/**
	 * Returns a deep copy of the entity.
	 *
	 * @since 0.1
	 *
	 * @return static
	 */
	public function copy() {
		return unserialize( serialize( $this ) );
	}

	/**
	 * @see ClaimListAccess::addClaim
	 *
	 * @since 0.3
	 * @deprecated since 1.0
	 *
	 * @param Claim $claim
	 *
	 * @throws InvalidArgumentException
	 */
	public function addClaim( Claim $claim ) {
	}

	/**
	 * @see ClaimAggregate::getClaims
	 *
	 * @since 0.3
	 * @deprecated since 1.0
	 *
	 * @return Claim[]
	 */
	public function getClaims() {
		return array();
	}

	/**
	 * Convenience function to check if the entity contains any claims.
	 *
	 * On top of being a convenience function, this implementation allows for doing
	 * the check without forcing an unstub in contrast to count( $this->getClaims() ).
	 *
	 * @since 0.2
	 * @deprecated since 1.0
	 *
	 * @return bool
	 */
	public function hasClaims() {
		return false;
	}

	/**
	 * @since 0.3
	 *
	 * @param Snak $mainSnak
	 *
	 * @return Claim
	 */
	public function newClaim( Snak $mainSnak ) {
		return new Claim( $mainSnak );
	}

	/**
	 * Returns an EntityDiff between $this and the provided Entity.
	 *
	 * @since 0.1
	 *
	 * @param Entity $target
	 * @param Differ|null $differ Since 0.4
	 *
	 * @return EntityDiff
	 * @throws InvalidArgumentException
	 */
	public final function getDiff( Entity $target, Differ $differ = null ) {
		if ( $this->getType() !== $target->getType() ) {
			throw new InvalidArgumentException( 'Can only diff between entities of the same type' );
		}

		if ( $differ === null ) {
			$differ = new MapDiffer( true );
		}

		$oldEntity = $this->getDiffArray();
		$newEntity = $target->getDiffArray();

		$diffOps = $differ->doDiff( $oldEntity, $newEntity );

		$claims = new Claims( $this->getClaims() );
		$diffOps['claim'] = $claims->getDiff( new Claims( $target->getClaims() ) );

		return EntityDiff::newForType( $this->getType(), $diffOps );
	}

	/**
	 * Create and returns an array based serialization suitable for EntityDiff.
	 *
	 * @return array[]
	 */
	protected function getDiffArray() {
		$array = array();

		$array['aliases'] = $this->getAllAliases();
		$array['label'] = $this->getLabels();
		$array['description'] = $this->getDescriptions();

		return $array;
	}

	/**
	 * Apply an EntityDiff to the entity.
	 *
	 * @since 0.4
	 *
	 * @param EntityDiff $patch
	 */
	public final function patch( EntityDiff $patch ) {
		$patcher = new MapPatcher();

		$this->setLabels( $patcher->patch( $this->getLabels(), $patch->getLabelsDiff() ) );
		$this->setDescriptions( $patcher->patch( $this->getDescriptions(), $patch->getDescriptionsDiff() ) );
		$this->setAllAliases( $patcher->patch( $this->getAllAliases(), $patch->getAliasesDiff() ) );

		$this->patchSpecificFields( $patch );
	}

	/**
	 * Patch fields specific to the type of entity.
	 * @see patch
	 *
	 * @since 1.0
	 *
	 * @param EntityDiff $patch
	 */
	protected function patchSpecificFields( EntityDiff $patch ) {
		// No-op, meant to be overridden in deriving classes to add specific behavior
	}

	/**
	 * Parses the claim GUID and returns the prefixed entity ID it contains.
	 *
	 * @since 0.3
	 * @deprecated since 0.4
	 *
	 * @param string $claimKey
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public static function getIdFromClaimGuid( $claimKey ) {
		$keyParts = explode( '$', $claimKey );

		if ( count( $keyParts ) !== 2 ) {
			throw new InvalidArgumentException( 'A claim key should have a single $ in it' );
		}

		return $keyParts[0];
	}

	/**
	 * Returns a list of all Snaks on this Entity. This includes at least the main snaks of
	 * Claims, the snaks from Claim qualifiers, and the snaks from Statement References.
	 *
	 * This is a convenience method for use in code that needs to operate on all snaks, e.g.
	 * to find all referenced Entities.
	 *
	 * @return Snak[]
	 */
	public function getAllSnaks() {
		$claims = $this->getClaims();
		$snaks = array();

		foreach ( $claims as $claim ) {
			$snaks = array_merge( $snaks, $claim->getAllSnaks() );
		}

		return $snaks;
	}

	/**
	 * @since 0.7.3
	 *
	 * @return Fingerprint
	 */
	public function getFingerprint() {
		return $this->fingerprint;
	}

	/**
	 * @since 0.7.3
	 *
	 * @param Fingerprint $fingerprint
	 */
	public function setFingerprint( Fingerprint $fingerprint ) {
		$this->fingerprint = $fingerprint;
	}

	/**
	 * @since 0.5
	 *
	 * @param string $idSerialization
	 *
	 * @return EntityId
	 */
	protected abstract function idFromSerialization( $idSerialization );

	/**
	 * Returns a type identifier for the entity.
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public abstract function getType();

}
