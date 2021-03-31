<?php

namespace Kimerikal\UtilBundle\Repository;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use http\Exception\BadConversionException;
use Kimerikal\UtilBundle\Entity\ExceptionUtil;
use Kimerikal\UtilBundle\Entity\StrUtil;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;

class KRepository extends EntityRepository
{
    public static $CURRENT_USER = null;

    /**
     * @param array $params
     * @param null $validator - Service validator
     * @return object|null
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(array $params, array $files = null, $validator = null)
    {
        if (empty($params) || count($params) === 0)
            throw new \Exception('No params found');

        $object = null;
        $type = $this->getClassName();
        if (array_key_exists('id', $params))
            $object = $this->getEntityManager()->getRepository($this->getClassName())->find($params['id']);

        if (!$object)
            $object = new $type;

        // TODO support data types: array, json_array
        $fields = $this->getClassMetaFields();
        $reader = new AnnotationReader();
        foreach ($fields as $key => $data) {
            $fieldKey = isset($data['columnName']) ? $data['columnName'] : $key;
            if (!array_key_exists($fieldKey, $params) || array_key_exists('id', $data))
                continue;

            $reflectionProperty = new \ReflectionProperty($this->getClassName(), $key);
            $fd = $reader->getPropertyAnnotation($reflectionProperty, 'Kimerikal\\UtilBundle\\Annotations\\FormData');
            try {
                $newValue = null;
                $setMethod = 'set' . \ucfirst($key);
                if (array_key_exists('targetEntity', $data)) {
                    if ((($data['type'] == ClassMetadataInfo::MANY_TO_MANY || $data['type'] == ClassMetadataInfo::TO_MANY))
                        && is_array($params[$fieldKey])) {
                        $setMethod = 'add' . Inflector::singularize(\ucfirst($key));
                        if (\method_exists($object, $setMethod)) {
                            foreach ($params[$fieldKey] as $obj) {
                                $bdObj = $this->getEntityManager()->getRepository($data['targetEntity'])->find($obj);
                                $object->$setMethod($bdObj);
                            }
                            continue;
                        }
                    } else
                        $newValue = $this->getEntityManager()->getRepository($data['targetEntity'])->find($params[$fieldKey]);
                } else
                    $newValue = $this->checkRequestData($params[$fieldKey], $data['type']);
                if (is_null($newValue) && !$data['nullable'])
                    throw new \RuntimeException('Param cannot be null');

                if (\method_exists($object, $setMethod))
                    $object->$setMethod($newValue);
            } catch (\RuntimeException $e) {
                ExceptionUtil::logException($e, 'KRepository::save::' . $type);
            }
        }

        if (!empty($files) && count($files) > 0) {
            foreach ($files as $key => $file) {
                $setter = 'set' . ucfirst($key);
                if (\method_exists($object, $setter)) {
                    $object->$setter($file);
                }
            }
        }

        if ($validator) {
            $errors = $validator->validate($object);
            if (count($errors) > 0) {
                $msg = '';
                foreach ($errors as $err) {
                    $msg .= strtoupper($err->getPropertyPath()) . ': ' . $err->getMessage() . '; ';
                }
                throw new \Exception(trim($msg));
            }
        }

        $this->getEntityManager()->persist($object);
        $this->getEntityManager()->flush();

        return $object;
    }

    /**
     * @param int $offset
     * @param int $limit
     * @param int $category
     * @param bool $onlyActive
     * @return Paginator|null
     */
    public function loadAll($offset = 0, $limit = 50, $onlyActive = false, $params = [])
    {
        if (empty($params))
            $params = $_REQUEST;

        $q = $this->createQueryBuilder('a')
            ->select('a')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $this->addJoins($q);
        if ($onlyActive && \property_exists($this->getClassName(), $this->enabledField())) {
            $q->andWhere('a.' . $this->enabledField() . ' = :enabled')
                ->setParameter('enabled', true);
        }

        $this->filterQuery($q, $params);
        $this->filter_load_all_query($q, $offset, $limit);

        $orderBy = $this->findOrderBy($this->loadAllOrderBy(), $params);
        if (!empty($orderBy)) {
            foreach ($orderBy as $key => $val) {
                $q->addOrderBy('a.' . $key, $val);
            }
        }

        try {
            return new KPaginator($q);
        } catch (\Exception $ex) {
            ExceptionUtil::logException($ex, 'KRepository::loadAll');
        }

        return null;
    }

    public function delete($object)
    {
        $this->_em->remove($object);
        $this->_em->flush();
    }

    /**
     * @param $pat
     * @param $filterBy
     * @param $searchBy
     * @param $orderBy
     * @param int $page
     * @param int $limit
     * @return array|null
     * @deprecated
     */
    public function search($pat, $filterBy, $searchBy, $orderBy, $page = 1, $limit = 50)
    {
        $list = null;
        $q = $this->createQueryBuilder('c')
            ->select('c');

        if (!empty($filterBy)) {
            for ($i = 0; $i < count($filterBy); $i++) {
                $q->addSelect('fil_' . $i)
                    ->innerJoin('c.' . $filterBy[$i]->name, 'fil_' . $i, 'WITH', 'fil_' . $i . '.id IN (' . implode(',', $filterBy[$i]->values) . ')');
            }
        }

        if (!empty($pat)) {
            $patArr = explode(' ', $pat);
            $patStrArr = array();

            foreach ($patArr as $query) {
                if (StrUtil::endsWith($query, 's')) {
                    $tmp = substr($query, 0, (strlen($query) - 1));
                    $query = $tmp;
                }

                $patStrArr[] = StrUtil::slug($query);
            }

            $pattern = implode('%', $patStrArr);
            $pattern_orig = str_replace(' ', '%', trim($pat));
            $qb = $this->getEntityManager()->createQueryBuilder();
            if (!empty($searchBy) && !empty($pattern)) {
                $slugUsed = false;
                $patternUsed = false;
                $str = '';
                $i = 0;
                foreach ($searchBy as $field) {
                    $link = ':pattern';
                    if (stripos($field, 'slug') !== false) {
                        $link = ':slug';
                        $slugUsed = true;
                    } else
                        $patternUsed = true;

                    $str .= ($i != 0 ? ' OR ' : '') . $field . ' LIKE ' . $link;
                    $i++;
                }

                $q->andWhere('(' . $str . ')');

                if ($slugUsed)
                    $q->setParameter('slug', '%' . $pattern . '%');

                if ($patternUsed)
                    $q->setParameter('pattern', '%' . $pattern_orig . '%');
            } else {
                $q->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->like('c.slug', ':slug'), $qb->expr()->like('c.description', ':slug'), $qb->expr()->like('c.phone', ':slug'), $qb->expr()->like('c.email', ':slug'), $qb->expr()->like('c.url', ':slug')
                    ))
                    ->setParameter('slug', '%' . $pattern . '%');
            }
        }

        if (!empty($orderBy)) {
            foreach ($orderBy as $key => $order) {
                $q->addOrderBy($key, $order);
            }
        } else {
            $this->loadAllOrderBy();
        }

        $this->filterQuery($q, $_REQUEST, 'c');

        //->setParameter('orig', '%' . $pattern_orig . '%');
        /* $q->setMaxResults($limit)
          ->setFirstResult($limit * ($page - 1)); */

        try {
            //$list = new Paginator($q);
            $query = $q->getQuery();
            $list = $query->getResult();
        } catch (\Exception $ex) {
            ExceptionUtil::logException($ex, 'KRepository::search');
        }

        return $list;
    }

    /**
     * @return int|mixed
     */
    public function count()
    {
        $count = 0;

        $q = $this
            ->createQueryBuilder('e')
            ->select('COUNT(e)')
            ->getQuery();

        try {
            $count = $q->getSingleScalarResult();
        } catch (\Exception $e) {
            $count = 0;
        }

        return $count;
    }

    /**
     * @param $data
     * @param $type
     * @return bool|float
     */
    protected function checkRequestData($data, $type)
    {
        switch ($type) {
            case "boolean";
                if (is_string($data)) {
                    if ($data == 'false')
                        return false;
                    if ($data == 'true')
                        return true;
                }

                if ($data == 1)
                    return true;
                if ($data == 0)
                    return false;

                if (!is_bool($data))
                    throw new \RuntimeException('Bad data type.');

                return $data;
            case "decimal":
                if (!$this->validateFloat($data))
                    throw new \RuntimeException('Bad data type.');

                return floatval(str_replace(',', '.', $data));;
            case "datetime":
                if ($this->validateDateTimeWithSeconds($data))
                    return \DateTime::createFromFormat('Y-m-d H:i:s', $data);
                if ($this->validateDateTimeWithoutSeconds($data))
                    return \DateTime::createFromFormat('Y-m-d H:i', $data);

                throw new \RuntimeException('Bad data type.');
                break;
            case "date":
                if (!$this->validateDate($data))
                    throw new \RuntimeException('Bad data type.');

                return \DateTime::createFromFormat('Y-m-d', $data);
        }

        return $data;
    }

    protected function validateFloat($test)
    {
        if (!is_scalar($test))
            return false;

        $type = gettype($test);
        if ($type === "float")
            return true;
        else
            return preg_match("/^\\d+\\.\\d+$/", str_replace(',', '.', abs($test))) === 1;
    }

    protected function validateDateTimeWithSeconds($date)
    {
        return preg_match("/^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01]) (00|[0-9]|1[0-9]|2[0-3]):([0-9]|[0-5][0-9]):([0-9]|[0-5][0-9])$/", $date) === 1;
    }

    protected function validateDateTimeWithoutSeconds($date)
    {
        return preg_match("/^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01]) (00|[0-9]|1[0-9]|2[0-3]):([0-9]|[0-5][0-9])$/", $date) === 1;
    }

    protected function validateDate($date)
    {
        return preg_match("/^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])$/", $date) === 1;
    }

    /**
     * Override to change param enabled or to leave it empty.
     */
    protected function enabledField()
    {
        return 'enabled';
    }

    /**
     * Override to change default order
     */
    protected function loadAllOrderBy()
    {
        return ['id' => 'DESC'];
    }

    protected function filter_load_all_query(QueryBuilder &$q, $offset, $limit)
    {
    }

    protected function addJoins(QueryBuilder &$q)
    {
    }

    protected function findOrderBy(array $default = null, array $params = null)
    {
        if (empty($params))
            return $default;

        $orderBy = [];
        foreach ($params as $key => $value) {
            if (stripos($key, 'orderby__') !== 0)
                continue;

            $tmp = explode('orderby__', $key);
            $values = explode(',', $value);
            foreach ($values as $val) {
                $orderBy[$val] = strtoupper($tmp[1]);
            }
        }

        if (count($orderBy) > 0)
            return $orderBy;

        return $default;
    }

    protected function searchPatternKeysToParams(array &$params, array $patterns, array $keys = null, string $object = '')
    {
        if (empty($keys))
            return;

        foreach ($keys as $key) {
            if (stripos($key, '|') && stripos($key, 'bundle') !== false && stripos($key, ':') !== false) {
                $tmp = explode('|', $key);
                $this->searchPatternKeysToParams($params, $patterns, $this->getEntityManager()->getRepository($tmp[0])->hookFilterPatternFields(), $tmp[1]);
                continue;
            }
            $params[(!empty($object) ? $object . ':' : '') . $key . '__like'] = $patterns;
        }
    }

    protected function filterQuery(QueryBuilder &$q, array $params = [], $letter = 'a')
    {
        if (empty($params) || count($params) === 0)
            return;

        $fields = $this->getClassMetaFields();
        $count = 1;
        $joins = [];
        $patterns = null;
        if (array_key_exists('searchStr', $params)) {
            $patterns = $this->preparePatternString($params['searchStr']);
            unset($params['searchStr']);
            $this->searchPatternKeysToParams($params, $patterns, $this->hookFilterPatternFields());
        }

        foreach ($params as $getKey => $param) {
            foreach ($fields as $key => $data) {
                if (stripos($getKey, $key) !== 0) {
                    $fieldKey = isset($data['columnName']) ? $data['columnName'] : $key;
                    if (stripos($getKey, $fieldKey) !== 0)
                        continue;
                }

                $prefix = $letter . '.';
                $field = $data['fieldName'];
                $tmp = explode('__', $getKey);
                if (strpos($tmp[0], ':') !== false) {
                    $joinTmp = explode(':', $tmp[0]);
                    if (!array_key_exists($joinTmp[0], $joins)) {
                        $joins[$joinTmp[0]] = $letter . $count . '.';
                        $q->innerJoin($prefix . $joinTmp[0], $letter . $count);
                    }

                    $prefix = $joins[$joinTmp[0]];
                    $field = $joinTmp[1];
                }

                if (count($tmp) === 1) {
                    $q->andWhere($prefix . $field . '= :param_' . $count)
                        ->setParameter('param_' . $count, $param);
                } else if (count($tmp) === 2) {
                    $operation = $this->queryActionDictionary($tmp[1]);
                    if ($operation === 'NOT IN' || $operation === 'IN') {
                        $q->andWhere($prefix . $field . ' ' . $operation . ' (:param_' . $count . ')');
                        if (!is_array($param)) {
                            if (strpos($param, '|') !== false) {
                                $param = explode('|', $param);
                            } else {
                                $param = [$param];
                            }
                        }

                        $q->setParameter('param_' . $count, $param, Connection::PARAM_STR_ARRAY);
                    } else if ($operation === 'LIKE') {
                        $likeVal = $patterns['original'];
                        if (stripos($field, 'slug') !== false)
                            $likeVal = $patterns['normalized'];
                        $q->orWhere($prefix . $field . ' LIKE :param_' . $count)
                            ->setParameter('param_' . $count, '%' . $likeVal . '%');
                    } else {
                        $q->andWhere($prefix . $field . ' ' . $operation . ' :param_' . $count)
                            ->setParameter('param_' . $count, $param);
                    }
                }

                $count++;
            }
        }

        // $this->patternFilter($q, $pattern, $letter);
    }

    protected function preparePatternString(string $patternOriginal)
    {
        $patStrArr = [];
        $patArr = explode(' ', $patternOriginal);
        foreach ($patArr as $query) {
            if (StrUtil::endsWith($query, 's')) {
                $tmp = substr($query, 0, (strlen($query) - 1));
                $query = $tmp;
            }

            $patStrArr[] = StrUtil::slug($query);
        }

        return [
            'normalized' => implode('%', $patStrArr),
            'original' => str_replace(' ', '%', trim($patternOriginal))
        ];
    }

    /**
     * @param QueryBuilder $q
     * @param string $searchStr
     * @param string $prefix
     */
    protected function patternFilter(QueryBuilder &$q, string $searchStr, string $prefix = '')
    {
        $searchBy = $this->hookFilterPatternFields();
        if (empty($searchStr) || empty($searchBy))
            return;

        $patterns = $this->preparePatternString($searchStr);
        $pattern = $patterns['normalized'];
        if (empty($pattern))
            return;

        $pattern_orig = $patterns['original'];
        $qb = $this->getEntityManager()->createQueryBuilder();

        $slugUsed = false;
        $patternUsed = false;
        $str = '';
        $i = 0;
        foreach ($searchBy as $field) {
            $link = ':pattern';
            if (stripos($field, 'slug') !== false) {
                $link = ':slug';
                $slugUsed = true;
            } else
                $patternUsed = true;

            $str .= ($i != 0 ? ' OR ' : '') . $prefix . '.' . $field . ' LIKE ' . $link;
            $i++;
        }

        $q->andWhere('(' . $str . ')');

        if ($slugUsed)
            $q->setParameter('slug', '%' . $pattern . '%');

        if ($patternUsed)
            $q->setParameter('pattern', '%' . $pattern_orig . '%');
    }

    protected function hookFilterPatternFields(): array
    {
        return [];
    }

    protected function queryActionDictionary($sign)
    {
        $dictionary = ['gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<=', 'in' => 'IN', 'notin' => 'NOT IN', 'like' => 'LIKE'];
        if (array_key_exists($sign, $dictionary))
            return $dictionary[$sign];

        return '=';
    }

    /**
     * @param string $view
     * @return array
     */
    public function getListFilterParams($view = 'list'): array
    {
        return [];
    }

    protected function _repo($repo)
    {
        return $this->getEntityManager()->getRepository($repo);
    }

    protected function getClassMetaFields()
    {
        $meta = $this->getClassMetadata();
        return array_merge($meta->fieldMappings, $meta->associationMappings);
    }

    protected function connection()
    {
        return $this->getEntityManager()->getConnection();
    }
}