<?php

namespace Interfaces\Entity;

use User\Entity\User;
use Utils\MetaDataMatcher;
use Doctrine\ORM\Mapping as ORM;
use Interfaces\Entity\ExerciseStatement;
use Utils\Exceptions\EntityOperatorException;
use Utils\Exceptions\EntityDataIntegrityException;

#[ORM\Entity(repositoryClass: "Interfaces\Repository\ProjectRepository")]
#[ORM\Table(name: "interfaces_projects")]
class Project implements \JsonSerializable, \Utils\JsonDeserializer
{
    const REG_PROJECT_NAME = "/./";
    const REG_PROJECT_DESCRIPTION = "/./";

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private $id;

    #[ORM\ManyToOne(targetEntity: "User\Entity\User")]
    #[ORM\JoinColumn(name: "user", referencedColumnName: "id", onDelete: "CASCADE")]
    private $user = null;

    #[ORM\ManyToOne(targetEntity: "Interfaces\Entity\ExercisePython", inversedBy: "projects")]
    #[ORM\JoinColumn(name: "id_exercise", referencedColumnName: "id")]
    private $exercise;

    #[ORM\ManyToOne(targetEntity: "Interfaces\Entity\ExerciseStatement", cascade: ["persist", "remove"])]
    #[ORM\JoinColumn(name: "exercise_statement_id", referencedColumnName: "id")]
    private $exerciseStatement;

    #[ORM\Column(name: "project_name", type: "string", length: 100, nullable: false, options: ["default" => "Unamed"])]
    private $name = "Unamed";

    #[ORM\Column(name: "interface", type: "string", length: 50, nullable: false)]
    private $interface;

    #[ORM\Column(name: "project_description", type: "string", length: 1000, nullable: true, options: ["default" => "No description"])]
    private $description = "No description";

    #[ORM\Column(name: "date_updated", type: "datetime", columnDefinition: "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")]
    private $dateUpdated;

    #[ORM\Column(name: "code", type: "string", length: 16777215, nullable: false)]
    private $code = "";

    #[ORM\Column(name: "code_language", type: "string", length: 16777215, nullable: true)]
    private $codeText = "";

    #[ORM\Column(name: "manually_modified", type: "boolean", nullable: false)]
    private $codeManuallyModified = false;

    #[ORM\Column(name: "is_public", type: "boolean", nullable: false, options: ["default" => false])]
    private $public = false;

    #[ORM\Column(name: "link", type: "string", length: 255, unique: true, nullable: false)]
    private $link = null;

    #[ORM\Column(name: "mode", type: "string", length: 20, nullable: true)]
    private $mode = null;

    #[ORM\Column(name: "is_deleted", type: "boolean", nullable: false, options: ["default" => false])]
    private $deleted = false;

    #[ORM\Column(name: "is_activity_solve", type: "boolean", nullable: false, options: ["default" => false])]
    private $activitySolve = false;

    #[ORM\Column(name: "is_exercise_creator", type: "boolean", nullable: false, options: ["default" => false])]
    private $isExerciseCreator = false;

    #[ORM\Column(name: "is_exercise_statement_creator", type: "boolean", nullable: false, options: ["default" => false])]
    private $isExerciseStatementCreator = false;

    #[ORM\Column(name: "shared_users", type: "text", nullable: true)]
    private $sharedUsers;

    #[ORM\Column(name: "shared_status", type: "integer", nullable: false, options: ["default" => 0])]
    private $sharedStatus = 0;

    #[ORM\Column(name: "options", type: "text", nullable: true)]
    private $options;

    public function __construct($name = 'Unamed', $description = 'No description')
    {
        $this->setName($name);
        $this->setDescription($description);
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        if (is_int($id) && $id > 0) {
            $this->id = $id;
        } else {
            throw new EntityDataIntegrityException("id needs to be integer and positive");
        }
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user)
    {
        if ($user instanceof User || $user == null) {
            $this->user = $user;
        } else {
            throw new EntityDataIntegrityException("user attribute needs to be an instance of User or null");
        }
    }

    public function getExercise()
    {
        return $this->exercise;
    }

    public function setExercise($exercise)
    {
        $this->exercise = $exercise;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        if (preg_match(self::REG_PROJECT_NAME, $this->name)) {
            $this->name = $name;
        } else {
            throw new EntityDataIntegrityException("Error while setting name: name does not match regex.");
        }
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function getInterface()
    {
        return $this->interface;
    }

    public function setInterface($interface)
    {
        $this->interface = $interface;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function getDateUpdated()
    {
        return $this->dateUpdated;
    }

    public function setDateUpdated($dateUpdated = null)
    {
        if ($dateUpdated == null) {
            $this->dateUpdated = new \DateTime();
        } else {
            $this->dateUpdated = new \DateTime($dateUpdated);
        }
    }

    public function getCode()
    {
        if (str_starts_with($this->code, '&lt;') || str_starts_with($this->code, "{&quot;")) {
            return htmlspecialchars_decode($this->code);
        }
        return $this->code;
    }

    public function setCode($code)
    {
        if (is_string($code)) {
            $this->code = $code;
        } else {
            throw new EntityDataIntegrityException("code needs to be string");
        }
    }

    public function getCodeText()
    {
        return htmlspecialchars_decode($this->codeText);
    }

    public function setCodeText($codeText)
    {
        if ($codeText == '' || is_string($codeText)) {
            $this->codeText = htmlspecialchars($codeText);
        } else {
            throw new EntityDataIntegrityException("codeText needs to be string");
        }
    }

    public function isCManuallyModified()
    {
        return $this->codeManuallyModified;
    }

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

    public function isPublic()
    {
        return $this->public;
    }

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

    public function getLink()
    {
        return $this->link;
    }

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

    public function isDeleted()
    {
        return $this->deleted;
    }

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

    public function isActivitySolve()
    {
        return $this->activitySolve;
    }

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

    public function getIsExerciseCreator()
    {
        return $this->isExerciseCreator;
    }

    public function setIsExerciseCreator($isExerciseCreator)
    {
        if (!is_bool($isExerciseCreator)) {
            throw new EntityDataIntegrityException("The exercise creator property has to be a boolean value");
        }

        $this->isExerciseCreator = $isExerciseCreator;
        return $this;
    }

    public function getIsExerciseStatementCreator()
    {
        return $this->isExerciseStatementCreator;
    }

    public function setIsExerciseStatementCreator($isExerciseStatementCreator)
    {
        if (!is_bool($isExerciseStatementCreator)) {
            throw new EntityDataIntegrityException("The exercise statement creator property has to be a boolean value");
        }

        $this->isExerciseStatementCreator = $isExerciseStatementCreator;
        return $this;
    }

    public function getExerciseStatement()
    {
        return $this->exerciseStatement;
    }

    public function setExerciseStatement($exerciseStatement)
    {
        if ($exerciseStatement === null || $exerciseStatement instanceof ExerciseStatement) {
            $this->exerciseStatement = $exerciseStatement;
            return $this;
        }
        throw new EntityDataIntegrityException("The exercise statement has to be an instance of ExerciseStatement class or null");
    }

    public function getSharedUsers()
    {
        return $this->sharedUsers;
    }

    public function setSharedUsers($sharedUsers)
    {
        $this->sharedUsers = $sharedUsers;
        return $this;
    }

    public function getSharedStatus()
    {
        return $this->sharedStatus;
    }

    public function setSharedStatus($sharedStatus)
    {
        $this->sharedStatus = $sharedStatus;
        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
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
            $this->setOptions($objectToCopyFrom->getOptions());
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

        $sharedUsers = @unserialize($this->getSharedUsers());
        if ($sharedUsers) {
            $sharedUsers = json_encode($sharedUsers);
        } else {
            $sharedUsers = null;
        }

        $options = null;
        if ($this->getOptions() != null) {
            $options = json_decode($this->getOptions());
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
            'interface' => $this->getInterface(),
            'exercise' => $this->getExercise(),
            'isExerciseCreator' => $this->getIsExerciseCreator(),
            'exerciseStatement' => $this->getExerciseStatement(),
            'isExerciseStatementCreator' => $this->getIsExerciseStatementCreator(),
            'sharedUsers' => $sharedUsers,
            'sharedStatus' => $this->getSharedStatus(),
            'options' => $options
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
