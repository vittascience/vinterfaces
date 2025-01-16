<?php

namespace Interfaces\Entity;

use User\Entity\User;
use Utils\MetaDataMatcher;
use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use Interfaces\Repository\ProjectLinkUserRepository;


#[ORM\Entity(repositoryClass: ProjectLinkUserRepository::class)]
#[ORM\Table(name: "interfaces_projects_link_users")]
class ProjectLinkUser
{

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user", referencedColumnName: "id", onDelete: "CASCADE")]
    private $user;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: "project", referencedColumnName: "id", onDelete: "CASCADE")]
    private $project;

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
