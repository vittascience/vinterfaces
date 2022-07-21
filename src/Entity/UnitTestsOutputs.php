<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\MetaDataMatcher;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;

/**
 * @ORM\Entity(repositoryClass="Interfaces\Repository\UnitTestsOutputsRepository")
 * @ORM\Table(name="python_tests_outputs")
 */
class UnitTestsOutputs implements \JsonSerializable, \Utils\JsonDeserializer
{
    /** 
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Interfaces\Entity\UnitTests")
     * @ORM\JoinColumn(name="id_unittest", referencedColumnName="id", onDelete="CASCADE")
     * @var UnitTests
     */
    private $unitTest;

    /**
     * @ORM\Column(name="value", type="string", length=1000, nullable=true)
     * @var string
     */
    private $value = null;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return UnitTests
     */
    public function getUnitTest()
    {
        return $this->unitTest;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param UnitTests $unitTest
     */
    public function setUnitTest($unitTest)
    {
        if ($unitTest instanceof UnitTests) {
            $this->unitTest = $unitTest;
        } else {
            throw new EntityDataIntegrityException("unitTest needs to be an instance of UnitTests");
        }
    }

    /**
     * @param string $value
     */
    public function setValue($value)
    {
        if (is_string($value) || $value === null) {
            $this->value = $value;
        } else {
            throw new EntityDataIntegrityException("value needs to be string");
        }
    }
    public function copy($objectToCopyFrom)
    {
        if ($objectToCopyFrom instanceof self) {
            $this->value = urldecode($objectToCopyFrom->value);
        } else {
            throw new EntityOperatorException("ObjectToCopyFrom attribute needs to be an instance of Tutorial");
        }
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->getId(),
            'unittest' => $this->getUnitTest(),
            'value' => $this->getValue()
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
