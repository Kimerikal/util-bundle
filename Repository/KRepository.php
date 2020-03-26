<?php

namespace Kimerikal\UtilBundle\Repository;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use http\Exception\BadConversionException;
use Kimerikal\UtilBundle\Entity\ExceptionUtil;
use Kimerikal\UtilBundle\Entity\StrUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;

class KRepository extends EntityRepository
{
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

        // TODO support data types: file, array, json_array, entity, entity array
        $meta = $this->getClassMetadata();
        $fields = array_merge($meta->fieldMappings, $meta->associationMappings);
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
                    $newValue = $this->getEntityManager()->getRepository($data['targetEntity'])->find($params[$fieldKey]);
                } else
                    $newValue = $this->checkRequestData($params[$fieldKey], $data['type']);
                if (is_null($newValue) && !$data['nullable'])
                    throw new BadConversionException('Param cannot be null');

                if (\method_exists($object, $setMethod))
                    $object->$setMethod($newValue);
            } catch (BadConversionException $e) {
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
    public function loadAll($offset = 0, $limit = 50, $category = 0, $onlyActive = false)
    {
        $q = $this->createQueryBuilder('c')
            ->select('c')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($category > 0) {
            $q->andWhere('c.category = :category')
                ->setParameter('category', $category);
        }

        if ($onlyActive) {
            $q->andWhere('c.enabled = :enabled')
                ->setParameter('enabled', true);
        }

        try {
            return new Paginator($q);
        } catch (\Exception $ex) {
            error_log($ex->getMessage());
        }

        return null;
    }

    public function delete($object) {
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
     */
    public function search($pat, $filterBy, $searchBy, $orderBy, $page = 1, $limit = 50)
    {
        $list = null;
        $q = $this->createQueryBuilder('c')
            ->select('c')
            ->where('c.enabled = 1');

        if (!empty($filterBy)) {
            for ($i = 0; $i < count($filterBy); $i++) {
                $q->addSelect('fil_' . $i)
                    ->innerJoin('c.' . $filterBy[$i]->name, 'fil_' . $i)
                    ->andWhere('fil_' . $i . '.id IN (' . implode(',', $filterBy[$i]->values) . ')');
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
        }

        //->setParameter('orig', '%' . $pattern_orig . '%');
        /* $q->setMaxResults($limit)
          ->setFirstResult($limit * ($page - 1)); */

        try {
            //$list = new Paginator($q);
            $query = $q->getQuery();
            $list = $query->getResult();
        } catch (\Exception $ex) {
            error_log($ex->getMessage());
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
                    throw new BadConversionException('Bad data type.');

                return $data;
            case "decimal":
                if (!$this->validateFloat($data))
                    throw new BadConversionException('Bad data type.');

                return floatval(str_replace(',', '.', $data));;
            case "datetime":
                if ($this->validateDateTimeWithSeconds($data))
                    return \DateTime::createFromFormat('Y-m-d H:i:s', $data);
                if ($this->validateDateTimeWithoutSeconds($data))
                    return \DateTime::createFromFormat('Y-m-d H:i', $data);

                throw new BadConversionException('Bad data type.');
                break;
            case "date":
                if (!$this->validateDate($data))
                    throw new BadConversionException('Bad data type.');

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
            return preg_match("/^\\d+\\.\\d+$/", str_replace(',', '.', $test)) === 1;
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
}