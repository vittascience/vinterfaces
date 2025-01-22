<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\MetaDataMatcher;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;

#[ORM\Entity(repositoryClass: "Interfaces\Repository\UnitTestsRepository")]
#[ORM\Table(name: "python_tests")]
class UnitTests implements \JsonSerializable, \Utils\JsonDeserializer
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private $id;

    #[ORM\ManyToOne(targetEntity: "Interfaces\Entity\ExercisePython")]
    #[ORM\JoinColumn(name: "id_exercise", referencedColumnName: "id", onDelete: "CASCADE")]
    private $exercise;

    #[ORM\Column(name: "hint", type: "string", length: 240, nullable: true)]
    private $hint = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getExercise(): ExercisePython
    {
        return $this->exercise;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setExercise($exercise): void
    {
        if ($exercise instanceof ExercisePython) {
            $this->exercise = $exercise;
        } else {
            throw new EntityDataIntegrityException("exercise needs to be an instance of ExercisePython");
        }
    }

    public function setHint($hint): void
    {
        if (is_string($hint)) {
            $this->hint = $hint;
        } else {
            throw new EntityDataIntegrityException("hint needs to be string");
        }
    }

    public function copy($objectToCopyFrom): void
    {
        if ($objectToCopyFrom instanceof self) {
            $this->hint = urldecode($objectToCopyFrom->hint);
        } else {
            throw new EntityOperatorException("ObjectToCopyFrom attribute needs to be an instance of Tutorial");
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'exercise' => $this->getExercise(),
            'hint' => $this->getHint()
        ];
    }

    public static function jsonDeserialize($jsonDecoded): self
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
