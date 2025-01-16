<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\MetaDataMatcher;

#[ORM\Entity(repositoryClass: "Interfaces\Repository\ExercisePythonFramesRepository")]
#[ORM\Table(name: "python_exercise_frames")]
class ExercisePythonFrames implements \JsonSerializable, \Utils\JsonDeserializer
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private $id;

    #[ORM\ManyToOne(targetEntity: "Interfaces\Entity\ExercisePython")]
    #[ORM\JoinColumn(name: "id_exercise", referencedColumnName: "id", onDelete: "CASCADE")]
    private $exercise;

    #[ORM\Column(name: "frame", type: "integer", nullable: false)]
    private $frame;

    #[ORM\Column(name: "id_component", type: "string", length: 255, nullable: false)]
    private $component;

    #[ORM\Column(name: "value", type: "string", nullable: false)]
    private $value;

    public function getExercise()
    {
        return $this->exercise;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function setExercise($exercise)
    {
        $this->exercise = $exercise;
        return $this;
    }

    public function getFrame()
    {
        return $this->frame;
    }

    public function setFrame(int $frame)
    {
        $this->frame = $frame;
        return $this;
    }

    public function getComponent()
    {
        return $this->component;
    }

    public function setComponent($component)
    {
        $this->component = $component;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->getId(),
            'exercise' => $this->getExercise(),
            'frame' => $this->getFrame(),
            'component' => $this->getComponent(),
            'value' => $this->getValue(),
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
