<?php

namespace Kimerikal\UtilBundle\Repository;

use Doctrine\ORM\QueryBuilder;
use Kimerikal\UtilBundle\Traits\KJsonSerialize;

class KPaginator implements \JsonSerializable
{
    use KJsonSerialize;

    /**
     * @var QueryBuilder
     * @KMK\KJsonHide()
     */
    private $queryBuilder;
    /** @var Array|null */
    private $list;
    /** @var int */
    private $total;
    /** @var int */
    private $offset;
    /** @var int */
    private $limit;
    /** @var int */
    private $remaining;

    public function __construct(QueryBuilder $q)
    {
        $this->queryBuilder = $q;
        $this->offset = !is_null($this->queryBuilder->getFirstResult()) ? $this->queryBuilder->getFirstResult() : -1;
        $this->limit = !is_null($this->queryBuilder->getMaxResults()) ? $this->queryBuilder->getMaxResults() : -1;
        $this->setResults();
        $this->setCount();
    }

    public function getList() {
        return $this->list;
    }

    public function getTotal(): int {
        return $this->total;
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

    public function setTotal() {
        $this->total = $this->redoWithCountQuery($this->queryBuilder);
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