<?php

namespace App\Trait;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;

trait PaginationTrait
{
    private const PER_PAGE = 100;

    private function getPageParam(Request $request): int
    {
        return max(1, (int) $request->query->get('page', 1));
    }

    private function paginateQb(QueryBuilder $qb, int $page, bool $all = false): array
    {
        if (!$all) {
            $qb->setFirstResult(($page - 1) * self::PER_PAGE)->setMaxResults(self::PER_PAGE);
        }
        $paginator = new Paginator($qb, false);
        $total     = count($paginator);
        $items     = iterator_to_array($paginator->getIterator());
        return [$items, $total];
    }

    private function paginateMeta(int $total, int $page): array
    {
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        return [
            'total'       => $total,
            'page'        => $page,
            'limit'       => self::PER_PAGE,
            'totalPages'  => $totalPages,
            'hasNextPage' => $page < $totalPages,
            'hasPrevPage' => $page > 1,
        ];
    }
}
