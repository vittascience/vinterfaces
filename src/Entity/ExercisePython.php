<?php

namespace Interfaces\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Utils\MetaDataMatcher;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;
use Interfaces\Entity\Project;

/**
 * @ORM\Entity(repositoryClass="Interfaces\Repository\ExercisePythonRepository")
 * @ORM\Table(name="python_exercises")
 */
class ExercisePython implements \JsonSerializable, \Utils\JsonDeserializer
{
    /** 
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;
    /**
     * @ORM\Column(name="link_solution", type="string", length=255, nullable=true)
     * @var string
     */
    private $linkSolution = null;
    /**
     * @ORM\OneToMany(targetEntity="Interfaces\Entity\Project",mappedBy="exercise")
     * @var Project
     */
    private $projects;

    /**
     * @ORM\Column(name="secret_word", type="string", length=100, nullable=true)
     * @var string
     */
    private $secretWord = null;
    /**
     * @ORM\Column(name="function_name", type="string", length=100, nullable=false)
     * @var string
     */
    private $functionName;

    public function __construct($functionName)
    {
        if ($functionName === null) {
            throw new EntityDataIntegrityException("functionName cannot be null");
        }

        if (is_string($functionName)) {
            $this->functionName = $functionName;
        } else {
            throw new EntityDataIntegrityException("functionName needs to be string");
        }
        $this->projects = new ArrayCollection();
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getLinkSolution()
    {
        return $this->linkSolution;
    }

    /**
     * @return Project
     */
    public function getProjects()
    {
        return $this->projects;
    }

    /**
     * @return string
     */
    public function getSecretWord()
    {
        return $this->secretWord;
    }

    /**
     * @return string
     */
    public function getFunctionName()
    {
        return $this->functionName;
    }

    /**
     * @param int $id
     * @return integer
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param string $linkSolution
     */
    public function setLinkSolution($id)
    {
        $this->linkSolution = $id;
    }

    /**
     * @param Project $project
     */
    public function setProject($project)
    {
        if ($project instanceof Project) {
            $this->project = $project;
        } else {
            throw new EntityDataIntegrityException("project needs to be an instance of Project");
        }
    }

    /**
     * @param string $secretWord
     */
    public function setSecretWord($secretWord)
    {
        $this->secretWord = $secretWord;
    }

    /**
     * @param string $functionName
     */
    public function setFunctionName($functionName)
    {
        if ($functionName === null) {
            throw new EntityDataIntegrityException("functionName cannot be null");
        }

        if (is_string($functionName)) {
            $this->functionName = $functionName;
        } else {
            throw new EntityDataIntegrityException("functionName needs to be string");
        }
    }
    public function copy($objectToCopyFrom)
    {
        if ($objectToCopyFrom instanceof self) {
            $this->functionName = urldecode($objectToCopyFrom->functionName);
            $this->secretWord = urldecode($objectToCopyFrom->secretWord);
            $this->linkSolution = urldecode($objectToCopyFrom->linkSolution);
        } else {
            throw new EntityOperatorException("ObjectToCopyFrom attribute needs to be an instance of Tutorial");
        }
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'linkSolution' => $this->getLinkSolution(),
            'projects' => $this->getProjects(),
            'secretWord' => $this->getSecretWord(),
            'functionName' => $this->getFunctionName(),
        ];
    }

    public static function jsonDeserialize($jsonDecoded)
    {
        $classInstance = new self("");
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
