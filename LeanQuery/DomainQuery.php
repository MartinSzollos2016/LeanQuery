<?php

declare(strict_types = 1);

namespace LeanQuery;

use ArrayObject;
use LeanMapper\Connection;
use LeanMapper\Entity;
use LeanMapper\Fluent;
use LeanMapper\IEntityFactory;
use LeanMapper\IMapper;
use LeanMapper\Result;
use LeanMapper\Row;

class DomainQuery
{

	public const PATTERN_IDENTIFIER = '[a-zA-Z0-9_\x7f-\xff]+'; // TODO: move to separate class in Lean Mapper

	private const JOIN_TYPE_INNER = 'join';

	private const JOIN_TYPE_LEFT = 'leftJoin';

	private const ORDER_ASC = 'ASC';

	private const ORDER_DESC = 'DESC';

	/** @var \LeanMapper\IEntityFactory */
	private $entityFactory;

	/** @var \LeanMapper\Connection */
	private $connection;

	/** @var \LeanMapper\IMapper */
	private $mapper;

	/** @var \LeanQuery\Hydrator */
	private $hydrator;

	/** @var \LeanQuery\QueryHelper */
	private $queryHelper;

	/** @var \LeanQuery\DomainQueryHelper */
	private $domainQueryHelper;

	/** @var \LeanQuery\Aliases */
	private $aliases;

	/** @var \LeanQuery\HydratorMeta */
	private $hydratorMeta;

	/** @var \stdClass */
	private $clauses;

	/** @var \ArrayObject */
	private $relationshipTables;

	/** @var array|null */
	private $results;

	/** @var \LeanMapper\Entity[]|null */
	private $entities;

	public function __construct(
		IEntityFactory $entityFactory,
		Connection $connection,
		IMapper $mapper,
		Hydrator $hydrator,
		QueryHelper $queryHelper
	)
	{
		$this->entityFactory = $entityFactory;
		$this->connection = $connection;
		$this->mapper = $mapper;
		$this->hydrator = $hydrator;
		$this->queryHelper = $queryHelper;

		$this->aliases = new Aliases();
		$this->hydratorMeta = new HydratorMeta();
		$this->clauses = (object) [
			'select' => [],
			'from' => null,
			'join' => [],
			'where' => [],
			'orderBy' => [],
		];
		$this->relationshipTables = new ArrayObject();
		$this->domainQueryHelper = new DomainQueryHelper(
			$mapper,
			$this->aliases,
			$this->hydratorMeta,
			$this->clauses,
			$this->relationshipTables
		);
	}

	public function select(string $aliases): self
	{
		$this->cleanCache();

		if (preg_match(
			'#^\s*(' . self::PATTERN_IDENTIFIER . '\s*,\s*)*(' . self::PATTERN_IDENTIFIER . ')\s*$#',
			$aliases
		) === false) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		$split = preg_split('#\s*,\s*#', trim($aliases));
		if ($split !== false) {
			$this->clauses->select += array_fill_keys($split, true);
		}

		return $this;
	}

	public function from(string $entityClass, string $alias): self
	{
		$this->cleanCache();

		if ($this->clauses->from !== null) {
			throw new \LeanMapper\Exception\InvalidMethodCallException();
		}
		$this->domainQueryHelper->setFrom($entityClass, $alias);

		return $this;
	}

	public function join(string $definition, string $alias): self
	{
		$this->cleanCache();

		$this->domainQueryHelper->addJoinByType($definition, $alias, self::JOIN_TYPE_INNER);
		return $this;
	}

	public function leftJoin(string $definition, string $alias): self
	{
		$this->cleanCache();

		$this->domainQueryHelper->addJoinByType($definition, $alias, self::JOIN_TYPE_LEFT);
		return $this;
	}

	/**
	 * @param string|string[]|array $args
	 *
	 * @return $this
	 */
	public function where($args): self
	{
		$this->cleanCache();
		$this->domainQueryHelper->addWhere(func_get_args());

		return $this;
	}

	public function orderBy(string $property, string $direction = self::ORDER_ASC): self
	{
		$this->cleanCache();

		$this->domainQueryHelper->addOrderBy($property, $direction);
		return $this;
	}

	public function createFluent(): Fluent
	{
		if ($this->clauses->from === null || $this->clauses->select === '') {
			throw new \LeanMapper\Exception\InvalidStateException();
		}
		$statement = $this->connection->command();

		foreach (array_keys($this->clauses->select) as $alias) { // SELECT
			$alias = (string) $alias;
			$statement->select(
				$this->queryHelper->formatSelect(
					$this->domainQueryHelper->getReflection($this->aliases->getEntityClass($alias)),
					$alias
				)
			);
			if (array_key_exists($alias, (array) $this->relationshipTables)) {
				call_user_func_array(
					[$statement, 'select'],
					array_merge(['%n.%n AS %n, %n.%n AS %n, %n.%n AS %n'], $this->relationshipTables[$alias])
				);
			}
		}

		$statement->from([$this->clauses->from['table'] => $this->clauses->from['alias']]); // FROM

		foreach ($this->clauses->join as $join) { // JOIN
			/** @var callable $cb */
			$cb = [$statement, $join['type']];
			call_user_func_array(
				$cb,
				array_merge(['%n AS %n'], $join['joinParameters'])
			);
			call_user_func_array(
				[$statement, 'on'],
				array_merge(['%n.%n = %n.%n'], $join['onParameters'])
			);
		}

		if ($this->clauses->where !== []) { // WHERE
			call_user_func_array([$statement, 'where'], $this->clauses->where);
		}

		foreach ($this->clauses->orderBy as $orderBy) { // ORDER BY
			$statement->orderBy('%n.%n', $orderBy[0], $orderBy[1]);
			if ($orderBy[2] === self::ORDER_DESC) {
				$statement->desc();
			}
		}

		return $statement;
	}

	public function getResult(string $alias): Result
	{
		if ($this->results === null) {
			$relationshipFilter = array_keys($this->clauses->select);
			foreach ($relationshipFilter as $filteredAlias) {
				if (array_key_exists($filteredAlias, (array) $this->relationshipTables)) {
					$relationshipFilter[] = $this->relationshipTables[$filteredAlias][0];
				}
			}
			$result = $this->createFluent()->execute();
			$this->results = $this->hydrator->buildResultsGraph(
				$result instanceof \Dibi\Result ? $result->setRowClass(null)->fetchAll() : [],
				$this->hydratorMeta,
				$relationshipFilter
			);
		}
		if (!array_key_exists($alias, $this->results)) {
			throw new \LeanMapper\Exception\InvalidArgumentException();
		}
		return $this->results[$alias];
	}

	/**
	 * @return \LeanMapper\Entity[]
	 */
	public function getEntities(): array
	{
		if ($this->entities === null) {
			$entities = [];
			$entityClass = $this->clauses->from['entityClass'];
			$result = $this->getResult($this->clauses->from['alias']);
			foreach ($result as $key => $row) {
				$entities[] = $entity = $this->entityFactory->createEntity($entityClass, new Row($result, $key));
				$entity->makeAlive($this->entityFactory, $this->connection, $this->mapper);
			}
			$this->entities = $this->entityFactory->createCollection($entities);
		}
		return $this->entities;
	}

	public function getEntity(): ?Entity
	{
		$entities = $this->getEntities();
		return ($entity = reset($entities)) !== false
			? $entity
			: null;
	}

	public function limit(int $limit): self
	{
		$this->cleanCache();

		$this->clauses->limit = $limit;
		return $this;
	}

	public function offset(int $offset): self
	{
		$this->cleanCache();

		$this->clauses->offset = $offset;
		return $this;
	}

	private function cleanCache(): void
	{
		$this->results = $this->entities = null;
	}

}
