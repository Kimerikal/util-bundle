<?php

namespace Kimerikal\UtilBundle\Repository;

use Doctrine\ORM\QueryBuilder;

class KPaginator
{
    /** @var QueryBuilder */
    private $queryBuilder;
    /** @var Array|null */
    private $list;
    /** @var int */
    private $count;
    /** @var int */
    private $offset;
    /** @var int */
    private $limit;
    /** @var int */
    private $remaining;

    public function __construct(QueryBuilder $q)
    {
        $this->queryBuilder = $q;
        $this->offset = $this->queryBuilder->getFirstResult();
        $this->limit = $this->queryBuilder->getMaxResults();
        $this->setResults();
        $this->setCount();
    }

    public function getList() {
        return $this->list;
    }

    public function count(): int {
        return $this->count;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getRemaining(): int
    {
        return $this->remaining;
    }

    public function setResults() {
        $this->list = $this->queryResults($this->queryBuilder);
    }

    public function setCount() {
        $this->count = $this->redoWithCountQuery($this->queryBuilder);
        $this->remaining = max(0, ($this->count - $this->offset - $this->limit));
    }

    public function queryResults(QueryBuilder $q)
    {
        return $this->queryBuilder->getQuery()->getResult();
    }

    public function redoWithCountQuery()
    {
        $aliases = $this->queryBuilder->getAllAliases();
        $alias = 'a.';
        if (count($aliases) > 0)
            $alias = $aliases[0] . '.';

        $this->queryBuilder->select('COUNT(' . $alias . 'id)')
            ->setFirstResult(0)
            ->setMaxResults(null);

        return $this->queryBuilder->getQuery()->getSingleScalarResult();
    }
}