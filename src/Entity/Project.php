<?php

namespace Interfaces\Entity;

use User\Entity\User;
use Utils\MetaDataMatcher;
use Doctrine\ORM\Mapping as ORM;
use Interfaces\Entity\ExerciseStatement;
use Utils\Exceptions\EntityOperatorException;
use Utils\Exceptions\EntityDataIntegrityException;

#[ORM\Table(name: 'interfaces_projects')]
#[ORM\Entity(repositoryClass: \Interfaces\Repository\ProjectRepository::class)]
class Project implements \JsonSerializable, \Utils\JsonDeserializer
{
    const REG_PROJECT_NAME = "/./";
    const REG_PROJECT_DESCRIPTION = "/./";

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private $id;
    /**
     * @var User
     */
    #[ORM\JoinColumn(name: 'user', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: \User\Entity\User::class)]
    private $user = null;

    #[ORM\JoinColumn(name: 'id_exercise', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \Interfaces\Entity\ExercisePython::class, inversedBy: 'projects')]
    private $exercise;

    #[ORM\JoinColumn(name: 'exercise_statement_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \Interfaces\Entity\ExerciseStatement::class, cascade: ['persist', 'remove'])]
    private $exerciseStatement;

    /**
     * @var string
     */
    #[ORM\Column(name: 'project_name', type: 'string', length: 100, nullable: false, options: ['default' => 'Unamed'])]
    private $name = "Unamed";

    /**
     * @var string
     */
    #[ORM\Column(name: 'interface', type: 'string', length: 50, nullable: false)]
    private $interface;
    /**
     * @var string
     */
    #[ORM\Column(name: 'project_description', type: 'string', length: 1000, nullable: true, options: ['default' => 'No description'])]
    private $description = "No description";
    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'date_updated', type: 'datetime', columnDefinition: 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP')]
    private $dateUpdated;
    /**
     * @var string
     */
    #[ORM\Column(name: 'code', type: 'string', length: 16777215, nullable: false)]
    private $code = "";
    /**
     * @var string
     */
    #[ORM\Column(name: 'code_language', type: 'string', length: 16777215, nullable: true)]
    private $codeText = "";
    /**
     * @var bool
     */
    #[ORM\Column(name: 'manually_modified', type: 'boolean', nullable: false)]
    private $codeManuallyModified = false;
    /**
     * @var bool
     */
    #[ORM\Column(name: 'is_public', type: 'boolean', nullable: false, options: ['default' => false])]
    private $public = false;
    /**
     * @var string
     */
    #[ORM\Column(name: 'link', type: 'string', length: 255, unique: true, nullable: false)]
    private $link = null;
    /**
     * @var string
     */
    #[ORM\Column(name: 'mode', type: 'string', length: 20, nullable: true)]
    private $mode = null;
    /**
     * @var bool
     */
    #[ORM\Column(name: 'is_deleted', type: 'boolean', nullable: false, options: ['default' => false])]
    private $deleted = false;

    /**
     * @var bool
     */
    #[ORM\Column(name: 'is_activity_solve', type: 'boolean', nullable: false, options: ['default' => false])]
    private $activitySolve = false;

    /**
     *
     * @var bool
     */
    #[ORM\Column(name: 'is_exercise_creator', type: 'boolean', nullable: false, options: ['default' => false])]
    private $isExerciseCreator = false;

    /**
     *
     * @var bool
     */
    #[ORM\Column(name: 'is_exercise_statement_creator', type: 'boolean', nullable: false, options: ['default' => false])]
    private $isExerciseStatementCreator = false;

    /**
     *
     * @var string
     */
    #[ORM\Column(name: 'shared_users', type: 'text', nullable: true)]
    private $sharedUsers;

    /**
     *
     * @var string
     */
    #[ORM\Column(name: 'shared_status', type: 'integer', nullable: false, options: ['default' => 0])]
    private $sharedStatus = 0;


    /**
     * @var String
     */
    #[ORM\Column(name: 'options', type: 'text', nullable: true)]
    private $options;


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
     * Get the value of exercise
     */
    public function getExercise()
    {
        return $this->exercise;
    }

    /**
     * Set the value of exercise
     *
     * @return  self
     */
    public function setExercise($exercise)
    {
        $this->exercise = $exercise;

        return $this;
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
        if(str_starts_with($this->code, '&lt;') || str_starts_with($this->code, "{&quot;")){
            return htmlspecialchars_decode($this->code) ;
        }
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
        return htmlspecialchars_decode($this->codeText);
    }

    /**
     * @param string $code_c
     */
    public function setCodeText($codeText)
    {
        if ($codeText == '' || is_string($codeText)) {
            $this->codeText = htmlspecialchars($codeText);
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

    /**
     * Get the value of isExerciseCreator
     *
     * @return  bool
     */
    public function getIsExerciseCreator()
    {
        return $this->isExerciseCreator;
    }

    /**
     * Set the value of isExerciseCreator
     *
     * @param  bool  $isExerciseCreator
     *
     * @return  self
     */
    public function setIsExerciseCreator($isExerciseCreator)
    {
        if (!is_bool($isExerciseCreator)) {
            throw new EntityDataIntegrityException("The exercise creator property has to be a boolean value");
        }

        $this->isExerciseCreator = $isExerciseCreator;
        return $this;
    }

    /**
     * Get the value of isExerciseStatementCreator
     *
     * @return  bool
     */
    public function getIsExerciseStatementCreator()
    {
        return $this->isExerciseStatementCreator;
    }

    /**
     * Set the value of isExerciseStatementCreator
     *
     * @param  bool  $isExerciseStatementCreator
     *
     * @return  self
     */
    public function setIsExerciseStatementCreator($isExerciseStatementCreator)
    {
        if (!is_bool($isExerciseStatementCreator)) {
            throw new EntityDataIntegrityException("The exercise statement creator property has to be a boolean value");
        }

        $this->isExerciseStatementCreator = $isExerciseStatementCreator;
        return $this;
    }

    /**
     * Get the value of exerciseStatement
     *
     * @return  
     */
    public function getExerciseStatement()
    {
        return $this->exerciseStatement;
    }

    /**
     * Set the value of exerciseStatement
     *
     * @param  ExerciseStatement  $exerciseStatement
     *
     * @return  self
     */
    public function setExerciseStatement($exerciseStatement)
    {
        if ($exerciseStatement === null || $exerciseStatement instanceof ExerciseStatement ) {
            $this->exerciseStatement = $exerciseStatement;
            return $this;
        }
        throw new EntityDataIntegrityException("The exercise statement has to be an instance of ExerciseStatement class or null");
    }

    /**
     * Get the value of shared user
     *
     * @return  string
     */
    public function getSharedUsers()
    {
        return $this->sharedUsers;
    }

    /**
     * Set the value of shared user
     *
     * @param  string  $sharedUser
     *
     * @return  self
     */
    public function setSharedUsers($sharedUsers)
    {
        $this->sharedUsers = $sharedUsers;

        return $this;
    }

    /**
     * Get the value of shared link rights
     *
     * @return  int
     */
    public function getSharedStatus()
    {
        return $this->sharedStatus;
    }

    /**
     * Set the value of shared link rights
     *
     * @param  int  $sharedStatus
     *
     * @return  self
     */
    public function setSharedStatus($sharedStatus)
    {
        $this->sharedStatus = $sharedStatus;

        return $this;
    }

    /**
     * Get the value of options
     *
     * @return  String
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set the value of options
     *
     * @param  String  $options
     *
     * @return  self
     */
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
