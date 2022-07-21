<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity(repositoryClass="Interfaces\Repository\PythonWhitelistRepository")
 * @ORM\Table(name="python_whitelist")
 */
class PythonWhitelist implements \JsonSerializable, \Utils\JsonDeserializer
{
    /** 
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(name="exercice_id", type="string", length=255, nullable=false)
     * @var string
     */
    private $exerciceId;


    //boolean
    /**
     * @ORM\Column(name="server_only", type="boolean", nullable=true)
     * @var boolean
     */
    private $serverOnly;


    /**
     * @return Int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getExerciceId(): ?string
    {
        return $this->exerciceId;
    }

    /**
     * @param string $exerciceId
     * @return PythonWhitelist
     */
    public function setExerciceId(string $exerciceId): PythonWhitelist
    {
        $this->exerciceId = $exerciceId;
        return $this;
    }

    /**
     * @param boolean $serverOnly
     * @return RefererPythonContainers
     */
    public function setServerOnly(bool $serverOnly): self
    {
        $this->serverOnly = $serverOnly;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getServerOnly(): ?bool
    {
        return $this->serverOnly;
    }



    public function jsonSerialize()
    {
        return [
            'id' => $this->getId(),
        ];
    }



    public static function jsonDeserialize($jsonDecoded)
    {
        $classInstance = new self();
        foreach ($jsonDecoded as $attributeName => $attributeValue) {
            $classInstance->{$attributeName} = $attributeValue;
        }
        return $classInstance;
    }
}
