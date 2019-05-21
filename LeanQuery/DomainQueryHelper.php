<?php

declare(strict_types = 1);

namespace LeanQuery;

use ArrayObject;
use Dibi;
use LeanMapper\IMapper;
use LeanMapper\Reflection\EntityReflection;
use LeanMapper\Relationship\HasMany;
use LeanMapper\Relationship\HasOne;
use stdClass;

class DomainQueryHelper
{

	/** @var \LeanMapper\IMapper */
	private $mapper;

	/** @var \LeanQuery\Aliases */
	private $aliases;

	/** @var \LeanQuery\HydratorMeta */
	private $hydratorMeta;

	/** @var \stdClass */
	private $clauses;

	/** @var \LeanMapper\Reflection\EntityReflection[] */
	private $reflections = [];

	/** @var int */
	private $indexer = 1;

	/** @var \ArrayObject */
	private $relationshipTables;

	/** @var int */
	private $awaitedParameters;

	public function __construct(
		IMapper $mapper,
		Aliases $aliases,
		HydratorMeta $hydratorMeta,
		stdClass $clauses,
		ArrayObject $relationshipTables
	)
	{
		$this->mapper = $mapper;
		$this->aliases = $aliases;
		$this->hydratorMeta = $hydratorMeta;
		$this->clauses = $clauses;
		$this->relationshipTables = $relationshipTables;
	}

	public function getReflection(string $entityClass): EntityReflection
	{
		if (!is_subclass_of($entityClass, 'LeanMapper\Entity')) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		if (!array_key_exists($entityClass, $this->reflections)) {
			$this->reflections[$entityClass] = $entityClass::getReflection($this->mapper);
		}
		return $this->reflections[$entityClass];
	}

	public function setFrom(string $entityClass, string $alias): void
	{
		$table = $this->mapper->getTable($entityClass);

		$this->aliases->addAlias($alias, $entityClass);
		$this->clauses->from = [
			'entityClass' => $entityClass,
			'table'       => $table,
			'alias'       => $alias,
		];

		$this->hydratorMeta->addTablePrefix($alias, $table);
		$this->hydratorMeta->addPrimaryKey($table, $this->mapper->getPrimaryKey($table));
	}

	public function addJoinByType(string $definition, string $alias, string $type): void
	{
		[$fromAlias, $viaProperty] = $this->parseDotNotation($definition);
		$entityReflection = $this->getReflection(
			$fromEntity = $this->aliases->getEntityClass($fromAlias)
		);
		$property = $entityReflection->getEntityProperty($viaProperty);
		if ($property === null || !$property->hasRelationship()) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		$relationship = $property->getRelationship();

		if ($relationship instanceof HasMany) {
			$relationshipTable = $relationship->getRelationshipTable();
			if ($relationshipTable === null) {
				throw new \LeanMapper\Exception\InvalidArgumentException();
			}
			$relTableAlias = $relationshipTable . $this->indexer;
			$this->clauses->join[] = [
				'type'           => $type,
				'joinParameters' => [
					$relationshipTable,
					$relTableAlias,
				],
				'onParameters'   => [
					$fromAlias,
					$primaryKey = $this->mapper->getPrimaryKey(
						$fromTable = $this->mapper->getTable($fromEntity)
					),
					$relTableAlias,
					$columnReferencingSourceTable = $relationship->getColumnReferencingSourceTable(),
				],
			];
			$this->hydratorMeta->addTablePrefix($relTableAlias, $relationshipTable);
			$this->hydratorMeta->addPrimaryKey(
				$relationshipTable,
				$relTablePrimaryKey = $this->mapper->getPrimaryKey($relationshipTable)
			);
			$this->hydratorMeta->addRelationship(
				$alias,
				new Relationship(
					$fromAlias,
					$fromTable,
					$columnReferencingSourceTable,
					Relationship::DIRECTION_REFERENCING,
					$relTableAlias,
					$relationshipTable,
					$primaryKey
				)
			);

			$this->clauses->join[] = [
				'type'           => $type,
				'joinParameters' => [
					$targetTable = $relationship->getTargetTable(),
					$alias,
				],
				'onParameters'   => [
					$relTableAlias,
					$columnReferencingTargetTable = $relationship->getColumnReferencingTargetTable(),
					$alias,
					$primaryKey = $this->mapper->getPrimaryKey($targetTable),
				],
			];
			$this->aliases->addAlias($alias, $property->getType());

			$this->hydratorMeta->addTablePrefix($alias, $targetTable);
			$this->hydratorMeta->addPrimaryKey($targetTable, $primaryKey);
			$this->hydratorMeta->addRelationship(
				$relTableAlias,
				new Relationship(
					$relTableAlias,
					$relationshipTable,
					$columnReferencingTargetTable,
					Relationship::DIRECTION_REFERENCED,
					$alias,
					$targetTable,
					$primaryKey
				)
			);

			$this->relationshipTables[$alias] = [
				$relTableAlias,
				$relTablePrimaryKey,
				$relTableAlias . QueryHelper::PREFIX_SEPARATOR . $relTablePrimaryKey,
				$relTableAlias,
				$columnReferencingSourceTable,
				$relTableAlias . QueryHelper::PREFIX_SEPARATOR . $columnReferencingSourceTable,
				$relTableAlias,
				$columnReferencingTargetTable,
				$relTableAlias . QueryHelper::PREFIX_SEPARATOR . $columnReferencingTargetTable,
			];

			$this->indexer++;
		} else {
			$this->clauses->join[] = [
				'type'           => $type,
				'joinParameters' => [
					$targetTable = $relationship->getTargetTable(),
					$alias,
				],
				'onParameters'   => $relationship instanceof HasOne
					?
					[
						$fromAlias,
						$relationshipColumn = $relationship->getColumnReferencingTargetTable(),
						$alias,
						$primaryKey = $this->mapper->getPrimaryKey($targetTable),
					]
					:
				[
					$fromAlias,
					$primaryKey = $this->mapper->getPrimaryKey(
						$fromTable = $this->mapper->getTable($fromEntity)
					),
					$alias,
					$columnReferencingSourceTable = $relationship->getColumnReferencingSourceTable(),
				],
			];
			$this->aliases->addAlias($alias, $property->getType());

			$this->hydratorMeta->addTablePrefix($alias, $targetTable);
			if ($relationship instanceof HasOne) {
				$this->hydratorMeta->addPrimaryKey($targetTable, $primaryKey);
				$this->hydratorMeta->addRelationship(
					$alias,
					new Relationship(
						$fromAlias,
						$this->mapper->getTable($fromEntity),
						$relationshipColumn,
						Relationship::DIRECTION_REFERENCED,
						$alias,
						$targetTable,
						$primaryKey
					)
				);
			} else {
				$this->hydratorMeta->addPrimaryKey(
					$targetTable,
					$targetTablePrimaryKey = $this->mapper->getPrimaryKey($targetTable)
				);
				$this->hydratorMeta->addRelationship(
					$fromAlias,
					new Relationship(
						$fromAlias,
						$fromTable,
						$columnReferencingSourceTable,
						Relationship::DIRECTION_REFERENCING,
						$alias,
						$targetTable,
						$primaryKey
					)
				);
			}
		}
	}

	/**
	 * @param array $arguments
	 */
	public function addWhere(array $arguments): void
	{
		$pattern = '/
			(\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*")| # string
			%([a-zA-Z~][a-zA-Z0-9~]{0,5})| # modifier
			(\?) | # placeholder
			(' . DomainQuery::PATTERN_IDENTIFIER . ')\.(' . DomainQuery::PATTERN_IDENTIFIER . ') # alias.property
		/xs';

		$this->awaitedParameters = 0;
		foreach ($arguments as $argument) {
			if ($this->awaitedParameters > 0) {
				$this->clauses->where[] = $argument;
				$this->awaitedParameters--;
			} else {
				if (!empty($this->clauses->where)) {
					if (isset(Dibi\Fluent::$separators['WHERE'])) {
						$this->clauses->where[] = Dibi\Fluent::$separators['WHERE'];
					}
				}
				$this->clauses->where[] = preg_replace_callback($pattern, [$this, 'translateMatches'], $argument);
			}
		}
		$this->awaitedParameters = null;
	}

	public function addOrderBy(string $property, string $direction): void
	{
		[$alias, $property] = $this->parseDotNotation($property);
		$entityReflection = $this->getReflection(
			$this->aliases->getEntityClass($alias)
		);
		$property = $entityReflection->getEntityProperty($property);

		if ($property->hasRelationship()) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		$this->clauses->orderBy[] = [$alias, $property->getColumn(), $direction];
	}

	////////////////////
	////////////////////

	/**
	 * @param string $definition
	 *
	 * @return array
	 */
	private function parseDotNotation(string $definition): array
	{
		$matches = [];
		if (!preg_match('#^\s*(' . DomainQuery::PATTERN_IDENTIFIER . ')\.(' . DomainQuery::PATTERN_IDENTIFIER
			. ')\s*$#', $definition, $matches)) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		return [$matches[1], $matches[2]];
	}

	/**
	 * @param array $matches
	 *
	 * @return string
	 */
	private function translateMatches(array $matches): string
	{
		if (!empty($matches[1])) { // quoted string
			return $matches[0];
		}
		if (!empty($matches[2]) or !empty($matches[3])) { // modifier or placeholder
			if ($matches[2] !== 'else' and $matches[2] !== 'end') {
				$this->awaitedParameters++;
			}
			return $matches[0];
		}

		$alias = $matches[4];
		$property = $matches[5];

		$entityClass = $this->aliases->getEntityClass($alias);
		$property = $this->getReflection($entityClass)->getEntityProperty($property);
		if ($property === null) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}

		$column = $property->getColumn();
		if ($column === null) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}

		return "[$alias.$column]";
	}

}
