<?php

declare(strict_types = 1);

namespace LeanQuery;

class Aliases
{

	/** @var array */
	private $aliases = [];

	/** @var array */
	private $index = [];

	public function addAlias(string $alias, string $entityClass): void
	{
		if (isset($this->aliases[$alias])) {
			throw new \LeanMapper\Exception\InvalidArgumentException("Alias $alias is already in use.");
		}
		$this->aliases[$alias] = $entityClass;
		if (!array_key_exists($entityClass, $this->index)) {
			$this->index[$entityClass] = $alias;
		}
	}

	public function getEntityClass(string $alias): string
	{
		if (!$this->hasAlias($alias)) {
			throw new \LeanMapper\Exception\InvalidArgumentException("Alias $alias was not found.");
		}
		return $this->aliases[$alias];
	}

	public function getAlias(string $entityClass): string
	{
		if (!array_key_exists($entityClass, $this->index)) {
			throw new \LeanMapper\Exception\InvalidArgumentException("Alias for $entityClass was not found.");
		}
		return $this->index[$entityClass];
	}

	public function hasAlias(string $alias): bool
	{
		return array_key_exists($alias, $this->aliases);
	}

}
