<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasField('deletedAt')) {
            return '';
        }

        $columnName = $targetEntity->getColumnName('deletedAt');

        return sprintf('%s.%s IS NULL', $targetTableAlias, $columnName);
    }
}
