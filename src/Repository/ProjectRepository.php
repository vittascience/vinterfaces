<?php

namespace Interfaces\Repository;

use Doctrine\ORM\EntityRepository;
use Interfaces\Entity\Project;
use User\Entity\User;

class ProjectRepository extends EntityRepository
{
    public function getSummaryPersonalProjects($data)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select("p.id, p.name, p.description, p.link, p.dateUpdated, p.interface, p.mode")
            ->from(Project::class, 'p')
            ->where('(p.user = :user AND p.deleted = :deleted AND p.interface = :interface)')
            ->setParameters(['user' => $data['user'], 'deleted' => $data['deleted'], 'interface' => $data['interface']])
            ->getQuery()
            ->getResult();
        return $query;
    }
    //"public" => true, "deleted" => false, "interface" => $data['interface'], "limit" => 30, "offset" => 0, "search" => ""
    public function getSummaryPublicProjects($data)
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select("p.id, p.name, p.description, p.link, p.dateUpdated, p.interface, p.mode, CONCAT(u.firstname,' ',u.surname) AS authorFullname, u.id AS authorId")
            ->from(Project::class, 'p')
            ->innerJoin(User::class, 'u', 'WITH', "p.user=u.id")
            ->where('(p.public = :public AND p.deleted = :deleted AND p.interface = :interface)')
            ->setParameters(['public' => $data['public'], 'deleted' => $data['deleted'], 'interface' => $data['interface']])
            ->orderBy('p.dateUpdated', 'DESC');

        if (!empty($data['search'])) {
            $qb->andWhere('(p.name LIKE :search OR p.description LIKE :search OR u.firstname LIKE :search OR u.surname LIKE :search)')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        // Fetch one extra row so the caller can tell whether more pages remain without a separate COUNT query.
        $qb->setFirstResult($data['offset'])
            ->setMaxResults($data['limit'] + 1);

        return $qb->getQuery()->getResult();
    }

    public function countPublicProjects($data)
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Project::class, 'p')
            ->innerJoin(User::class, 'u', 'WITH', "p.user=u.id")
            ->where('(p.public = :public AND p.deleted = :deleted AND p.interface = :interface)')
            ->setParameters(['public' => $data['public'], 'deleted' => $data['deleted'], 'interface' => $data['interface']]);

        if (!empty($data['search'])) {
            $qb->andWhere('(p.name LIKE :search OR p.description LIKE :search OR u.firstname LIKE :search OR u.surname LIKE :search)')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getByLinks(array $links)
    {
        if (count($links) === 0) {
            return [];
        }
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('p')
            ->from(Project::class, 'p')
            ->where('p.link IN (:links) AND p.deleted = :deleted')
            ->setParameter('links', $links)
            ->setParameter('deleted', false)
            ->getQuery()
            ->getResult();
    }
}
