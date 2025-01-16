<?php

namespace Interfaces\Entity;

use Doctrine\ORM\Mapping as ORM;
use Interfaces\Repository\PythonWhitelistRepository;

#[ORM\Entity(repositoryClass: PythonWhitelistRepository::class)]
#[ORM\Table(name: "python_whitelist")]
class PythonWhitelist implements \JsonSerializable, \Utils\JsonDeserializer
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private $id;

    #[ORM\Column(name: "exercice_id", type: "string", length: 255, nullable: false)]
    private string $exerciceId;

    #[ORM\Column(name: "server_only", type: "boolean", nullable: true)]
    private ?bool $serverOnly = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExerciceId(): ?string
    {
        return $this->exerciceId;
    }

    public function setExerciceId(string $exerciceId): self
    {
        $this->exerciceId = $exerciceId;
        return $this;
    }

    public function setServerOnly(?bool $serverOnly): self
    {
        $this->serverOnly = $serverOnly;
        return $this;
    }

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
