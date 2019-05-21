<?php

declare(strict_types = 1);

namespace LeanQuery;

class HydratorMeta
{

	/** @var array */
	private $tablesByPrefixes = [];

	/** @var array */
	private $primaryKeysByTables = [];

	/** @var array|\LeanQuery\Relationship[] */
	private $relationships = [];

	public function getTableByPrefix(string $prefix): string
	{
		if (!array_key_exists($prefix, $this->tablesByPrefixes)) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		return $this->tablesByPrefixes[$prefix];
	}

	/**
	 * @return array
	 */
	public function getTablesByPrefixes(): array
	{
		return $this->tablesByPrefixes;
	}

	public function addTablePrefix(string $prefix, string $table): void
	{
		if (array_key_exists($prefix, $this->tablesByPrefixes)) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		$this->tablesByPrefixes[$prefix] = $table;
	}

	public function getPrimaryKeyByTable(string $table): string
	{
		if (!array_key_exists($table, $this->primaryKeysByTables)) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		return $this->primaryKeysByTables[$table];
	}

	public function addPrimaryKey(string $table, string $primaryKey): void
	{
		if (array_key_exists($table, $this->primaryKeysByTables)) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		$this->primaryKeysByTables[$table] = $primaryKey;
	}

	/**
	 * @param array $filter
	 *
	 * @return array|\LeanQuery\Relationship[]
	 */
	public function getRelationships(?array $filter = null): array
	{
		return $filter === null ? $this->relationships
			: array_intersect_key($this->relationships, array_fill_keys($filter, true));
	}

	/**
	 * @param string $alias
	 * @param \LeanQuery\Relationship|string $relationship
	 */
	public function addRelationship(string $alias, $relationship): void
	{
		if (array_key_exists($alias, $this->relationships)) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		$this->relationships[$alias] = $relationship instanceof Relationship
			? $relationship
			: Relationship::createFromString($relationship);
	}

}
