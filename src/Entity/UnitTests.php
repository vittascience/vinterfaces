<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\MetaDataMatcher;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;

/**
 * @ORM\Entity(repositoryClass="Interfaces\Repository\UnitTestsRepository")
 * @ORM\Table(name="python_tests")
 */
class UnitTests implements \JsonSerializable, \Utils\JsonDeserializer
{
    /** 
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Interfaces\Entity\ExercisePython")
     * @ORM\JoinColumn(name="id_exercise", referencedColumnName="id", onDelete="CASCADE")
     * @var ExercisePython
     */
    private $exercise;
    /**
     * @ORM\Column(name="hint", type="string", length=240, nullable=true)
     * @var string
     */
    private $hint =  null;

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ExercisePython
     */
    public function getExercise()
    {
        return $this->exercise;
    }

    /**
     * @return string
     */
    public function getHint()
    {
        return $this->hint;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param ExercisePython $exercise
     */
    public function setExercise($exercise)
    {
        if ($exercise instanceof ExercisePython) {
            $this->exercise = $exercise;
        } else {
            throw new EntityDataIntegrityException("exercise needs to be an instance of ExercisePython");
        }
    }

    /**
     * @param string $hint
     */
    public function setHint($hint)
    {
        if (is_string($hint)) {
            $this->hint = $hint;
        } else {
            throw new EntityDataIntegrityException("hint needs to be string");
        }
    }


    public function copy($objectToCopyFrom)
    {
        if ($objectToCopyFrom instanceof self) {
            $this->hint = urldecode($objectToCopyFrom->hint);
        } else {
            throw new EntityOperatorException("ObjectToCopyFrom attribute needs to be an instance of Tutorial");
        }
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'exercise' => $this->getExercise(),
            'hint' => $this->getHint()
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
