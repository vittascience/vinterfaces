<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\MetaDataMatcher;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;

#[ORM\Entity(repositoryClass: "Interfaces\Repository\UnitTestsInputsRepository")]
#[ORM\Table(name: "python_tests_inputs")]
class UnitTestsInputs implements \JsonSerializable, \Utils\JsonDeserializer
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private $id;

    #[ORM\ManyToOne(targetEntity: "Interfaces\Entity\UnitTests", cascade: ["remove"])]
    #[ORM\JoinColumn(name: "id_unittest", referencedColumnName: "id", onDelete: "CASCADE")]
    private $unitTest;

    #[ORM\Column(name: "value", type: "string", length: 1000, nullable: true)]
    private $value = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUnitTest(): UnitTests
    {
        return $this->unitTest;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setUnitTest(UnitTests $unitTest): void
    {
        if ($unitTest instanceof UnitTests) {
            $this->unitTest = $unitTest;
        } else {
            throw new EntityDataIntegrityException("unitTest needs to be an instance of UnitTests");
        }
    }

    public function setValue(?string $value): void
    {
        if (is_string($value) || $value === null) {
            $this->value = $value;
        } else {
            throw new EntityDataIntegrityException("value needs to be string");
        }
    }

    public function copy($objectToCopyFrom): void
    {
        if ($objectToCopyFrom instanceof self) {
            $this->value = urldecode($objectToCopyFrom->value);
        } else {
            throw new EntityOperatorException("ObjectToCopyFrom attribute needs to be an instance of Tutorial");
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'unittest' => $this->getUnitTest(),
            'value' => $this->getValue()
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
