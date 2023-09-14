<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\MetaDataMatcher;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;

/**
 * @ORM\Entity(repositoryClass="Interfaces\Repository\ExercisePythonFramesRepository")
 * @ORM\Table(name="python_exercise_frames")
 */
class ExercisePythonFrames implements \JsonSerializable, \Utils\JsonDeserializer
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
     */
    private $exercise;

    /**
     * @ORM\Column(name="frame", type="integer", nullable=false)
     * @var integer
     */
    private $frame;

    /**
     * @ORM\Column(name="id_component", type="string", length=255, nullable=false)
     */
    private $component;

    /**
     * @ORM\Column(name="value", type="string", nullable=false)
     */
    private $value;



    /**
     * Get the value of exercise
     */
    public function getExercise()
    {
        return $this->exercise;
    }

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
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
     * Get the value of frame
     *
     * @return  string
     */
    public function getFrame()
    {
        return $this->frame;
    }

    /**
     * Set the value of frame
     *
     * @param  string  $frame
     *
     * @return  self
     */
    public function setFrame(string $frame)
    {
        $this->frame = $frame;

        return $this;
    }

    /**
     * Get the value of component
     */
    public function getComponent()
    {
        return $this->component;
    }

    /**
     * Set the value of component
     *
     * @return  self
     */
    public function setComponent($component)
    {
        $this->component = $component;

        return $this;
    }

    /**
     * Get the value of value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set the value of value
     *
     * @return  self
     */
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
