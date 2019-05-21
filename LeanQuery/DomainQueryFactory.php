<?php

declare(strict_types = 1);

namespace LeanQuery;

use LeanMapper\Connection;
use LeanMapper\IEntityFactory;
use LeanMapper\IMapper;

class DomainQueryFactory
{

	/** @var \LeanMapper\IEntityFactory */
	private $enityFactory;

	/** @var \LeanMapper\Connection */
	private $connection;

	/** @var \LeanMapper\IMapper */
	private $mapper;

	/** @var \LeanQuery\Hydrator */
	private $hydrator;

	/** @var \LeanQuery\QueryHelper */
	private $queryHelper;

	public function __construct(
		IEntityFactory $enityFactory,
		Connection $connection,
		IMapper $mapper,
		Hydrator $hydrator,
		QueryHelper $queryHelper
	)
	{
		$this->enityFactory = $enityFactory;
		$this->connection = $connection;
		$this->mapper = $mapper;
		$this->hydrator = $hydrator;
		$this->queryHelper = $queryHelper;
	}

	public function createQuery(): DomainQuery
	{
		return new DomainQuery(
			$this->enityFactory,
			$this->connection,
			$this->mapper,
			$this->hydrator,
			$this->queryHelper
		);
	}

}
