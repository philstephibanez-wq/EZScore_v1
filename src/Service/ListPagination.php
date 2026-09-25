<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

final class ListPagination
{
    /**
     * @return array{rows:list<object>, page:int, pages:int, total:int, per_page:int}
     */
    public function paginate(
        QueryBuilder $qb,
        Request $request,
        string $rootAlias,
        string $pageParameter = 'page',
        int $perPage = 25,
    ): array {
        $page = max(1, $request->query->getInt($pageParameter, 1));
        $perPage = max(10, min(100, $perPage));

        $count = clone $qb;
        $count->resetDQLPart('orderBy');

        $total = (int) $count
            ->select(sprintf('COUNT(DISTINCT %s.id)', $rootAlias))
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $rows = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'rows' => $rows,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => $perPage,
        ];
    }

    public function letter(Request $request, string $parameter = 'letter'): ?string
    {
        $letter = mb_strtoupper(trim((string) $request->query->get($parameter, '')));
        return preg_match('/^[A-Z]$/', $letter) ? $letter : null;
    }

    public function query(Request $request, string $parameter = 'q'): string
    {
        return trim((string) $request->query->get($parameter, ''));
    }

    /** @return list<string> */
    public function alphabet(): array
    {
        return range('A', 'Z');
    }
}
