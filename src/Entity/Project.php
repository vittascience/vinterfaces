<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;
use Utils\MetaDataMatcher;
use User\Entity\User;

/**
 * @ORM\Entity(repositoryClass="Interfaces\Repository\ProjectRepository")
 * @ORM\Table(name="interfaces_projects")
 */
class Project implements \JsonSerializable, \Utils\JsonDeserializer
{
    const REG_PROJECT_NAME = "/./";
    const REG_PROJECT_DESCRIPTION = "/./";

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;
    /**
     * @ORM\ManyToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="user", referencedColumnName="id", onDelete="CASCADE")
     * @var User
     */
    private $user = null;
    /**
     * @ORM\Column(name="project_name", type="string", length=100, nullable=false, options={"default":"Unamed"})
     * @var string
     */
    private $name = "Unamed";

    /**
     * @ORM\Column(name="interface", type="string", length=50, nullable=false)
     * @var string
     */
    private $interface;
    /**
     * @ORM\Column(name="project_description", type="string", length=1000, nullable=true, options={"default":"No description"})
     * @var string
     */
    private $description = "No description";
    /**
     * @ORM\Column(name="date_updated", type="datetime", columnDefinition="TIMESTAMP DEFAULT CURRENT_TIMESTAMP")
     * @var \DateTime
     */
    private $dateUpdated;
    /**
     * @ORM\Column(name="code", type="string", length=16777215, nullable=false)
     * @var string
     */
    private $code = "";
    /**
     * @ORM\Column(name="code_language", type="string", length=16777215, nullable=false)
     * @var string
     */
    private $codeText = "";
    /**
     * @ORM\Column(name="manually_modified", type="boolean", nullable=false)
     * @var bool
     */
    private $codeManuallyModified = false;
    /**
     * @ORM\Column(name="is_public", type="boolean", nullable=false, options={"default":false})
     * @var bool
     */
    private $public = false;
    /**
     * @ORM\Column(name="link", type="string", length=255, unique=true, nullable=false)
     * @var string
     */
    private $link = null;
    /**
     * @ORM\Column(name="mode", type="string", length=20, nullable=true)
     * @var string
     */
    private $mode = null;
    /**
     * @ORM\Column(name="is_deleted", type="boolean", nullable=false, options={"default":false})
     * @var bool
     */
    private $deleted = false;

    /**
     * @ORM\Column(name="is_activity_solve", type="boolean", nullable=false, options={"default":false})
     * @var bool
     */
    private $activitySolve = false;

    /**
     * Project constructor
     * @param string $name
     * @param string $description
     */
    public function __construct($name = 'Unamed', $description = 'No description')
    {
        $this->setName($name);
        $this->setDescription($description);
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        if (is_int($id) && $id > 0) {
            $this->id = $id;
        } else {
            throw new EntityDataIntegrityException("id needs to be integer and positive");
        }
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
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        if (preg_match(self::REG_PROJECT_NAME, $this->name)) {
            $this->name = $name;
        } else {
            throw new EntityDataIntegrityException("Error while setting name: name does not match regex.");
        }
    }
    /**
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param string $name
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    /**
     * @return string
     */
    public function getInterface()
    {
        return $this->interface;
    }

    /**
     * @param string $interface
     */
    public function setInterface($interface)
    {
        $this->interface = $interface;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        /*  if (preg_match(self::REG_PROJECT_DESCRIPTION, $description)) { */
        $this->description = $description;
        /*  } else {
            throw new EntityDataIntegrityException("description needs to be string and between 1 and 1000 characters");
        } */
    }

    /**
     * @return \DateTime
     */
    public function getDateUpdated()
    {
        return $this->dateUpdated;
    }

    /**
     * @param string $dateUpdated
     */
    public function setDateUpdated($dateUpdated = null)
    {
        if ($dateUpdated == null) {
            $this->dateUpdated = new \DateTime();
        } else {
            $this->dateUpdated = new \DateTime($dateUpdated);
        }
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode($code)
    {
        if (is_string($code)) {
            $this->code = $code;
        } else {
            throw new EntityDataIntegrityException("code needs to be string");
        }
    }

    /**
     * @return string
     */
    public function getCodeText()
    {
        return $this->codeText;
    }

    /**
     * @param string $code_c
     */
    public function setCodeText($codeText)
    {
        if (is_string($codeText)) {
            $this->codeText = $codeText;
        } else {
            throw new EntityDataIntegrityException("codeText needs to be string");
        }
    }

    /**
     * @return bool
     */
    public function isCManuallyModified()
    {
        return $this->codeManuallyModified;
    }

    /**
     * @param bool $codeManuallyModified
     */
    public function setCodeManuallyModified($codeManuallyModified)
    {
        if ($codeManuallyModified === null) {
            throw new EntityDataIntegrityException("codeManuallyModified attribute should not be null");
        }
        $codeManuallyModified = filter_var($codeManuallyModified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (is_bool($codeManuallyModified)) {
            $this->codeManuallyModified = $codeManuallyModified;
        } else {
            throw new EntityDataIntegrityException("codeManuallyModified needs to be a boolean");
        }
    }
    /**
     * @return bool
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * @param bool $isPublic
     */
    public function setPublic($isPublic)
    {
        if ($isPublic === null) {
            throw new EntityDataIntegrityException("isPublic attribute should not be null");
        }
        $isPublic = filter_var($isPublic, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (is_bool($isPublic)) {
            $this->public = $isPublic;
        } else {
            throw new EntityDataIntegrityException("isPublic needs to be boolean");
        }
    }

    /**
     * @return string
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @param string $link
     */
    public function setLink($link)
    {
        if ($link === null) {
            throw new EntityDataIntegrityException("link attribute should not be null");
        }
        if (is_string($link)) {
            $this->link = $link;
        } else {
            throw new EntityDataIntegrityException("link needs to be string");
        }
    }


    /**
     * isDeleted
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * @param  bool $deleted
     */
    public function setDeleted($deleted)
    {
        if ($deleted === null) {
            throw new EntityDataIntegrityException("deleted attribute should not be null");
        }
        $deleted = filter_var($deleted, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (is_bool($deleted)) {
            $this->deleted = $deleted;
        } else {
            throw new EntityDataIntegrityException("deleted needs to be boolean");
        }
    }

    /**
     * isActivity
     *
     * @return bool
     */
    public function isActivitySolve()
    {
        return $this->activitySolve;
    }

    /**
     * @param  bool $activitySolve
     */
    public function setActivitySolve($activitySolve)
    {
        if ($activitySolve === null) {
            throw new EntityDataIntegrityException("activitySolve attribute should not be null");
        }
        $deleted = filter_var($activitySolve, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (is_bool($activitySolve)) {
            $this->activitySolve = $activitySolve;
        } else {
            throw new EntityDataIntegrityException("activitySolve needs to be boolean");
        }
    }

    public function copy($objectToCopyFrom)
    {
        if ($objectToCopyFrom instanceof self) {
            $this->setName(urldecode($objectToCopyFrom->getName()));
            $this->setDescription(urldecode($objectToCopyFrom->getDescription()));
            if (gettype($objectToCopyFrom->getDateUpdated()) == "string") {
                $this->setDateUpdated($objectToCopyFrom->getDateUpdated());
            } else if (get_class($objectToCopyFrom->getDateUpdated()) == "DateTime") {
                $this->setDateUpdated($objectToCopyFrom->getDateUpdated()->format('Y-m-d\TH:i:s.u'));
            } else {
                $this->setDateUpdated($objectToCopyFrom->getDateUpdated()->date);
            }
            $this->setCode($objectToCopyFrom->getCode());
            $this->setCodeText($objectToCopyFrom->getCodeText());
            $this->setCodeManuallyModified($objectToCopyFrom->isCManuallyModified());
            $this->setPublic($objectToCopyFrom->isPublic());
            $this->setInterface($objectToCopyFrom->getInterface());
            $this->setDeleted($objectToCopyFrom->isDeleted());
            $this->setActivitySolve($objectToCopyFrom->isActivitySolve());
        } else {
            throw new EntityOperatorException("ObjectToCopyFrom attribute needs to be an instance of Project");
        }
    }


    public function jsonSerialize()
    {
        $user = $this->getUser();
        if ($user != null) {
            $user = $this->getUser()->jsonSerialize();
        }
        return [
            'id' => $this->getId(),
            'user' => $user,
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'dateUpdated' => $this->getDateUpdated(),
            'code' => $this->getCode(),
            'codeText' => $this->getCodeText(),
            'codeManuallyModified' => $this->isCManuallyModified(),
            'public' => $this->isPublic(),
            'link' => $this->getLink(),
            'mode' => $this->getMode(),
            'interface' => $this->getInterface()
        ];
    }

    public static function jsonDeserialize($jsonDecoded)
    {
        $classInstance = new self();
        foreach ($jsonDecoded as $attributeName => $attributeValue) {
            $attributeType = MetaDataMatcher::matchAttributeType(
                self::class,
                $attributeName
            );
            if ($attributeType instanceof \DateTime) {
                $date = new \DateTime();
                $date->setTimestamp($attributeValue);
                $attributeValue = $date;
            }
            $classInstance->{$attributeName} = $attributeValue;
        }
        return $classInstance;
    }
}
