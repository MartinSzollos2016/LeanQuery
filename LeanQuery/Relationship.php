<?php

declare(strict_types = 1);

namespace LeanQuery;

class Relationship
{

	public const DIRECTION_REFERENCED = '=>';

	public const DIRECTION_REFERENCING = '<=';

	public const DIRECTION_BOTH = '<=>';

	private const RE_IDENTIFIER = '[a-zA-Z0-9_-]+'; // TODO: move to separate class in Lean Mapper

	/** @var string */
	private $sourcePrefix;

	/** @var string */
	private $sourceTable;

	/** @var string */
	private $relationshipColumn;

	/** @var string */
	private $direction;

	/** @var string */
	private $targetPrefix;

	/** @var string */
	private $targetTable;

	/** @var string */
	private $primaryKeyColumn;

	public function __construct(
		string $sourcePrefix,
		string $sourceTable,
		string $relationshipColumn,
		string $direction,
		string $targetPrefix,
		string $targetTable,
		string $primaryKeyColumn
	)
	{
		if ($direction !== self::DIRECTION_REFERENCED and $direction !== self::DIRECTION_REFERENCING and $direction
			!== self::DIRECTION_BOTH) {
			throw new \LeanMapper\Exception\InvalidArgumentException("Invalid relationship direction given: $direction");
		}
		$this->sourcePrefix = $sourcePrefix;
		$this->sourceTable = $sourceTable;
		$this->relationshipColumn = $relationshipColumn;
		$this->direction = $direction;
		$this->targetPrefix = $targetPrefix;
		$this->targetTable = $targetTable;
		$this->primaryKeyColumn = $primaryKeyColumn;
	}

	public static function createFromString(string $definition): self
	{
		$matches = [];
		// brackets hell matching <sourcePrefix>[(<sourceTable)].<relationshipColumn><direction><targetPrefix>[(<targetTable)].<primaryKeyColumn>
		if (preg_match('#^\s*(' . self::RE_IDENTIFIER . ')(?:\((' . self::RE_IDENTIFIER . ')\))?\.('
				. self::RE_IDENTIFIER . ')\s*(' . self::DIRECTION_REFERENCED . '|' . self::DIRECTION_REFERENCING . '|'
				. self::DIRECTION_BOTH . ')\s*(' . self::RE_IDENTIFIER . ')(?:\((' . self::RE_IDENTIFIER . ')\))?\.('
				. self::RE_IDENTIFIER . ')\s*$#', $definition, $matches) === false) {
			throw new \LeanMapper\Exception\InvalidArgumentException("Invalid relationships definition given: $definition");
		}
		if ($matches[4] === self::DIRECTION_REFERENCED) {
			$direction = self::DIRECTION_REFERENCED;
		} elseif ($matches[4] === self::DIRECTION_REFERENCING) {
			$direction = self::DIRECTION_REFERENCING;
		} else {
			$direction = self::DIRECTION_BOTH;
		}
		return new self(
			$matches[1],
			$matches[2] !== '' ? $matches[2] : $matches[1],
			$matches[3],
			$direction,
			$matches[5],
			$matches[6] !== '' ? $matches[6] : $matches[5],
			$matches[7]
		);
	}

	public function getSourcePrefix(): string
	{
		return $this->sourcePrefix;
	}

	public function getSourceTable(): string
	{
		return $this->sourceTable;
	}

	public function getRelationshipColumn(): string
	{
		return $this->relationshipColumn;
	}

	public function getDirection(): string
	{
		return $this->direction;
	}

	public function getTargetPrefix(): string
	{
		return $this->targetPrefix;
	}

	public function getTargetTable(): string
	{
		return $this->targetTable;
	}

	public function getPrimaryKeyColumn(): string
	{
		return $this->primaryKeyColumn;
	}

}
