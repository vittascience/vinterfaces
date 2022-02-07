<?php

namespace Interfaces\Entity;

use User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Interfaces\Repository\LtiProjectRepository;
use Utils\Exceptions\EntityDataIntegrityException;

/**
 * @ORM\Entity(repositoryClass=LtiProjectRepository::class)
 * @ORM\Table(name="interfaces_lti_projects")
 */
class LtiProject {
    

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $user;

    /**
     * @ORM\Column(name="user_project_link",type="string",nullable="false")
     */
    private $userProjectLink = '';

    /**
     * @ORM\Column(name="lti_course_id",type="string",nullable="false")
     */
    private $ltiCourseId = '';

    /**
     * @ORM\Column(name="lti_resource_id", type="string", nullable="false")
     */
    private $ltiResourceLinkId = '';

    /**
     * @ORM\Column(name="is_submitted", type="boolean",nullable="false")
     */
    private $isSubmitted = false;

    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of user
     */ 
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the value of user
     *
     * @return  self
     */ 
    public function setUser($user)
    {
        if(!($user instanceof User)){
            throw new EntityDataIntegrityException("The user has to be an instance of User class");
        }
        $this->user = $user;

        return $this;
    }

    /**
     * Get the value of userProjectLink
     */ 
    public function getUserProjectLink()
    {
        return $this->userProjectLink;
    }

    /**
     * Set the value of userProjectLink
     *
     * @return  self
     */ 
    public function setUserProjectLink($userProjectLink)
    {
        if(!is_string($userProjectLink)){
            throw new EntityDataIntegrityException("The user project link has to be a string");
        }
        $this->userProjectLink = $userProjectLink;

        return $this;
    }

    /**
     * Get the value of ltiCourseId
     */ 
    public function getLtiCourseId()
    {
        return $this->ltiCourseId;
    }

    /**
     * Set the value of ltiCourseId
     *
     * @return  self
     */ 
    public function setLtiCourseId($ltiCourseId)
    {
        if(!is_string($ltiCourseId)){
            throw new EntityDataIntegrityException("The lti course id has to be a string");
        }
        $this->ltiCourseId = $ltiCourseId;

        return $this;
    }

    /**
     * Get the value of ltiResourceLinkId
     */ 
    public function getLtiResourceLinkId()
    {
        return $this->ltiResourceLinkId;
    }

    /**
     * Set the value of ltiResourceLinkId
     *
     * @return  self
     */ 
    public function setLtiResourceLinkId($ltiResourceLinkId)
    {
        if(!is_string($ltiResourceLinkId)){
            throw new EntityDataIntegrityException("The resource link id has to be a string");
        }
        $this->ltiResourceLinkId = $ltiResourceLinkId;

        return $this;
    }

    /**
     * Get the value of isSubmitted
     */ 
    public function getIsSubmitted()
    {
        return $this->isSubmitted;
    }

    /**
     * Set the value of isSubmitted
     *
     * @return  self
     */ 
    public function setIsSubmitted($isSubmitted)
    {
        if(!is_bool($isSubmitted)){
            throw new EntityDataIntegrityException("The isSubmitted variable has to be a boolean value ");
        }
        $this->isSubmitted = $isSubmitted;

        return $this;
    }
}