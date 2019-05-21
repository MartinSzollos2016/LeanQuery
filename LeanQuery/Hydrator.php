<?php

declare(strict_types = 1);

namespace LeanQuery;

use LeanMapper\Connection;
use LeanMapper\IMapper;
use LeanMapper\Result;

class Hydrator
{

	/** @var \LeanMapper\Connection */
	private $connection;

	/** @var \LeanMapper\IMapper */
	private $mapper;

	public function __construct(Connection $connection, IMapper $mapper)
	{
		$this->connection = $connection;
		$this->mapper = $mapper;
	}

	/**
	 * @param \Dibi\Row[]|array[]     $data
	 * @param \LeanQuery\HydratorMeta $hydratorMeta
	 * @param array|null              $relationshipsFilter
	 *
	 * @return array
	 */
	public function buildResultsGraph(
		array $data,
		HydratorMeta $hydratorMeta,
		?array $relationshipsFilter = null
	): array
	{
		$results = array_fill_keys(array_keys($hydratorMeta->getTablesByPrefixes()), []);

		$index = [];
		foreach ($data as $row) {
			$currentPrimaryKeys = [];
			foreach ($hydratorMeta->getTablesByPrefixes() as $prefix => $table) {
				$alias = $prefix . QueryHelper::PREFIX_SEPARATOR . $hydratorMeta->getPrimaryKeyByTable($table);
				if (isset($row[$alias])) {
					$currentPrimaryKeys[$prefix] = $row[$alias];
				}
			}
			foreach ($row as $field => $value) {
				if (!isset($index[$field])) {
					$index[$field] = explode(QueryHelper::PREFIX_SEPARATOR, $field, 2);
				}
				[$prefix, $field] = $index[$field];
				if (
					!isset($results[$prefix]) || !isset($currentPrimaryKeys[$prefix])
					|| isset($results[$prefix][$currentPrimaryKeys[$prefix]][$field])
				) {
					continue;
				}
				if (!isset($results[$prefix][$currentPrimaryKeys[$prefix]])) {
					$results[$prefix][$currentPrimaryKeys[$prefix]] = [];
				}
				$results[$prefix][$currentPrimaryKeys[$prefix]][$field] = $value;
			}
		}
		foreach ($results as $prefix => $rows) {
			$results[$prefix] = Result::createInstance(
				$rows,
				$hydratorMeta->getTableByPrefix($prefix),
				$this->connection,
				$this->mapper
			);
		}
		$relationships = $hydratorMeta->getRelationships($relationshipsFilter);
		if ($relationships !== []) {
			$this->linkResults($results, $relationships);
		}
		return $results;
	}

	////////////////////
	////////////////////

	/**
	 * @param array                     $results
	 * @param \LeanQuery\Relationship[] $relationships
	 *
	 * @return array
	 */
	private function linkResults(array $results, array $relationships): array
	{
		foreach ($relationships as $relationship) {
			if (!isset($results[$relationship->getSourcePrefix()])
				|| !isset($results[$relationship->getTargetPrefix()])) {
				throw new \LeanMapper\Exception\InvalidArgumentException('Missing relationship identified by given prefix. Deal with it :-P.');
			}
			if ($relationship->getDirection() === Relationship::DIRECTION_REFERENCED) {
				$results[$relationship->getSourcePrefix()]->setReferencedResult(
					$results[$relationship->getTargetPrefix()],
					$relationship->getTargetTable(),
					$relationship->getRelationshipColumn()
				);
			}
			if ($relationship->getDirection() === Relationship::DIRECTION_REFERENCING) {
				$results[$relationship->getSourcePrefix()]->setReferencingResult(
					$results[$relationship->getTargetPrefix()],
					$relationship->getTargetTable(),
					$relationship->getRelationshipColumn()
				);
			}
			if ($relationship->getDirection() === Relationship::DIRECTION_BOTH) {
				$results[$relationship->getSourcePrefix()]->setReferencedResult(
					$results[$relationship->getTargetPrefix()],
					$relationship->getTargetTable(),
					$relationship->getRelationshipColumn()
				);
				$results[$relationship->getTargetPrefix()]->setReferencingResult(
					$results[$relationship->getSourcePrefix()],
					$relationship->getSourceTable(),
					$relationship->getRelationshipColumn()
				);
			}
		}
		return $results;
	}

}
