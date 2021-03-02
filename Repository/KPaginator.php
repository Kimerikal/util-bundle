<?php

namespace Kimerikal\UtilBundle\Repository;

use Doctrine\ORM\QueryBuilder;

class KPaginator
{
    /** @var QueryBuilder */
    private $queryBuilder;
    /** @var KRepository */
    private $repository;
    private $result;
    private $count;

    public function __construct(QueryBuilder $q, KRepository $repository)
    {
        $this->queryBuilder = $q;
        $this->repository = $repository;
        $this->setResults();
        $this->setCount();
    }

    public function getResult() {
        return $this->result;
    }

    public function count(): int {
        return $this->count;
    }

    public function setResults() {
        $this->result = $this->repository->queryResults($this->queryBuilder);
    }

    public function setCount() {
        $this->count = $this->repository->redoWithCountQuery($this->queryBuilder);
    }
}