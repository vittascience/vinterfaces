<?php

namespace Interfaces\Repository;

use Doctrine\ORM\EntityRepository;
use Interfaces\Entity\Project;

class ProjectRepository extends EntityRepository
{
    public function getSummaryPersonalProjects($data)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select("p.id, p.name, p.description, p.link, p.dateUpdated, p.interface")
            ->from(Project::class, 'p')
            ->where('(p.user = :user AND p.deleted = :deleted AND p.interface = :interface)')
            ->setParameters(['user' => $data['user'], 'deleted' => $data['deleted'], 'interface' => $data['interface']])
            ->getQuery()
            ->getResult();
        return $query;
    }
    //"public" => true, "deleted" => false, "interface" => $data['interface']
    public function getSummaryPublicProjects($data)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select("p.id, p.name, p.description, p.link, p.dateUpdated, p.interface")
            ->from(Project::class, 'p')
            ->where('(p.public = :public AND p.deleted = :deleted AND p.interface = :interface)')
            ->setParameters(['public' => $data['public'], 'deleted' => $data['deleted'], 'interface' => $data['interface']])
            ->getQuery()
            ->getResult();
        return $query;
    }
}
