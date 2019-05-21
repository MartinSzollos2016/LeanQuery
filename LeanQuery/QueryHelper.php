<?php

declare(strict_types = 1);

namespace LeanQuery;

use LeanMapper\Reflection\EntityReflection;
use LeanMapper\Reflection\Property;

class QueryHelper
{

	public const PREFIX_SEPARATOR = '__';

	/**
	 * @param \LeanMapper\Reflection\EntityReflection $entityReflection
	 * @param string                                  $tableAlias
	 * @param string                                  $prefix
	 *
	 * @return array
	 * @internal param string $entityClass
	 */
	public function formatSelect(EntityReflection $entityReflection, string $tableAlias, ?string $prefix = null): array
	{
		isset($prefix) or $prefix = $tableAlias;
		$fields = [];

		foreach ($entityReflection->getEntityProperties() as $property) {
			if (($column = $property->getColumn()) === null) {
				continue;
			}
			$fields["$tableAlias.$column"] = $prefix . self::PREFIX_SEPARATOR . $column;
		}

		return $fields;
	}

	public function formatColumn(Property $property, string $tableAlias, ?string $prefix = null): string
	{
		isset($prefix) or $prefix = $tableAlias;

		$column = $property->getColumn();
		if ($column === null) {
			throw new \LeanMapper\Exception\InvalidArgumentException(sprintf(
				'Missing low-level column for property %s.',
				$property->getName()
			));
		}

		return $prefix . self::PREFIX_SEPARATOR . $column;
	}

}
