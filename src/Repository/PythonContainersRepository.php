<?php

namespace Interfaces\Repository;

use Doctrine\ORM\EntityRepository;


class PythonContainersRepository extends EntityRepository
{
    public function getFreeContainer()
    {
        return $this->createQueryBuilder('a')
            ->select("a")
            ->where('a.isAttribued IS NULL AND a.accessKey IS NULL ')
            ->andWhere('a.status =:param')
            ->setParameter(":param", "RUNNING")
            ->getQuery()
            ->getResult();
    }
}
