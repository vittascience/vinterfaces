<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;
use Utils\MetaDataMatcher;
use User\Entity\User;

#[ORM\Table(name: 'interfaces_projects_link_users')]
#[ORM\Entity(repositoryClass: \Interfaces\Repository\ProjectLinkUserRepository::class)]
class ProjectLinkUser
{


    /**
     * @var User
     */
    #[ORM\JoinColumn(name: 'user', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: \User\Entity\User::class)]
    private $user;
    /**
     * @var Project
     */
    #[ORM\JoinColumn(name: 'project', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: \Interfaces\Entity\Project::class)]
    private $project;


    /**
     * Project constructor
     * @param string $name
     * @param string $description
     */
    public function __construct(User $user, Project $project)
    {
        $this->setUser($user);
        $this->setProject($project);
    }


    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser($user)
    {
        if ($user instanceof User || $user == null) {
            $this->user = $user;
        } else {
            throw new EntityDataIntegrityException("user attribute needs to be an instance of User or null");
        }
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param Project $project
     */
    public function setProject($project)
    {
        if ($project instanceof Project || $project == null) {
            $this->project = $project;
        } else {
            throw new EntityDataIntegrityException("project attribute needs to be an instance of Project or null");
        }
    }

    public function jsonSerialize()
    {
        $user = $this->getUser();
        if ($user != null) {
            $user = $this->getUser()->jsonSerialize();
        }
        $project = $this->getProject();
        if ($project != null) {
            $project = $this->getProject()->jsonSerialize();
        }
        return [
            'user' => $user,
            'project' => $project
        ];
    }

    public static function jsonDeserialize($jsonDecoded)
    {
        $classInstance = new self(new User(), new Project());
        foreach ($jsonDecoded as $attributeName => $attributeValue) {
            $attributeType = MetaDataMatcher::matchAttributeType(
                self::class,
                $attributeName
            );
            $classInstance->{$attributeName} = $attributeValue;
        }
        return $classInstance;
    }
}
