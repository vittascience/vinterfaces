<?php

namespace Interfaces\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Utils\MetaDataMatcher;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;
use Interfaces\Entity\Project;

#[ORM\Entity(repositoryClass: "Interfaces\Repository\ExercisePythonRepository")]
#[ORM\Table(name: "python_exercises")]
class ExercisePython implements \JsonSerializable, \Utils\JsonDeserializer
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private $id;

    #[ORM\Column(name: "link_solution", type: "string", length: 255, nullable: true)]
    private ?string $linkSolution = null;

    #[ORM\OneToMany(targetEntity: "Interfaces\Entity\Project", mappedBy: "exercise")]
    private $projects;

    #[ORM\Column(name: "secret_word", type: "string", length: 100, nullable: true)]
    private ?string $secretWord = null;

    #[ORM\Column(name: "function_name", type: "string", length: 100, nullable: false)]
    private string $functionName;

    public function __construct(string $functionName)
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

    public function getId(): int
    {
        return $this->id;
    }

    public function getLinkSolution(): ?string
    {
        return $this->linkSolution;
    }

    public function getProjects(): ArrayCollection
    {
        return $this->projects;
    }

    public function getSecretWord(): ?string
    {
        return $this->secretWord;
    }

    public function getFunctionName(): string
    {
        return $this->functionName;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setLinkSolution(?string $linkSolution): void
    {
        $this->linkSolution = $linkSolution;
    }

    public function setProject(Project $project): void
    {
        if ($project instanceof Project) {
            $this->projects[] = $project;
        } else {
            throw new EntityDataIntegrityException("project needs to be an instance of Project");
        }
    }

    public function setSecretWord(?string $secretWord): void
    {
        $this->secretWord = $secretWord;
    }

    public function setFunctionName(string $functionName): void
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

    public function copy($objectToCopyFrom): void
    {
        if ($objectToCopyFrom instanceof self) {
            $this->functionName = urldecode($objectToCopyFrom->functionName);
            $this->secretWord = urldecode($objectToCopyFrom->secretWord);
            $this->linkSolution = urldecode($objectToCopyFrom->linkSolution);
        } else {
            throw new EntityOperatorException("ObjectToCopyFrom attribute needs to be an instance of Tutorial");
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'linkSolution' => $this->getLinkSolution(),
            'projects' => $this->getProjects(),
            'secretWord' => $this->getSecretWord(),
            'functionName' => $this->getFunctionName(),
        ];
    }

    public static function jsonDeserialize($jsonDecoded): self
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
