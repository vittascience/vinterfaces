<?php

namespace Interfaces\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use User\Entity\User;

#[ORM\Entity(repositoryClass: "Interfaces\Repository\PythonContainersRepository")]
#[ORM\Table(name: "python_containers")]
class PythonContainers implements \JsonSerializable, \Utils\JsonDeserializer
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private $id;

    #[ORM\Column(name: "task_arn", type: "string", length: 255, nullable: false)]
    private $taskArn;

    #[ORM\OneToOne(targetEntity: "User\Entity\User")]
    #[ORM\JoinColumn(name: "is_attribued", referencedColumnName: "id", onDelete: "CASCADE")]
    private $isAttribued;

    #[ORM\Column(name: "access_key", type: "string", length: 255, nullable: true)]
    private $accessKey;

    #[ORM\Column(name: "public_ip", type: "string", length: 255, nullable: true)]
    private $publicIp;

    #[ORM\Column(name: "created_at", type: "datetime", columnDefinition: "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")]
    private $createdAt;

    #[ORM\Column(name: "updated_at", type: "datetime", columnDefinition: "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")]
    private $updatedAt;

    #[ORM\OneToOne(targetEntity: "User\Entity\User")]
    #[ORM\JoinColumn(name: "user", referencedColumnName: "id", onDelete: "CASCADE")]
    private $user;

    #[ORM\Column(name: "container_key", type: "string", length: 255, nullable: true)]
    private $containerKey;

    #[ORM\Column(name: "status", type: "string", length: 255, nullable: false)]
    private $status;

    #[ORM\Column(name: "rule_node", type: "string", length: 255, nullable: true)]
    private $ruleNode;

    #[ORM\Column(name: "rule_vnc", type: "string", length: 255, nullable: true)]
    private $ruleVnc;

    #[ORM\Column(name: "target_node", type: "string", length: 255, nullable: true)]
    private $targetNode;

    #[ORM\Column(name: "target_vnc", type: "string", length: 255, nullable: true)]
    private $targetVnc;

    #[ORM\Column(name: "link", type: "string", length: 7, nullable: true)]
    private $link;

    #[ORM\Column(name: "priority", type: "string", length: 25, nullable: true)]
    private $priority;

    #[ORM\Column(name: "process", type: "integer", nullable: false)]
    private $process = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskArn(): ?string
    {
        return $this->taskArn;
    }

    public function setTaskArn(string $taskArn): self
    {
        $this->taskArn = $taskArn;
        return $this;
    }

    public function getIsAttribued(): ?User
    {
        return $this->isAttribued;
    }

    public function setIsAttribued(User $isAttribued): self
    {
        $this->isAttribued = $isAttribued;
        return $this;
    }

    public function getPublicIp(): ?string
    {
        return $this->publicIp;
    }

    public function setPublicIp(string $publicIp): self
    {
        $this->publicIp = $publicIp;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user)
    {
        $this->user = $user;
        return $this;
    }

    public function getContainerKey(): ?string
    {
        return $this->containerKey;
    }

    public function setContainerKey(string $containerKey): self
    {
        $this->containerKey = $containerKey;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getTargetNode(): ?string
    {
        return $this->targetNode;
    }

    public function setTargetNode(string $targetNode): self
    {
        $this->targetNode = $targetNode;
        return $this;
    }

    public function getTargetVnc(): ?string
    {
        return $this->targetVnc;
    }

    public function setTargetVnc(string $targetVnc): self
    {
        $this->targetVnc = $targetVnc;
        return $this;
    }

    public function getRuleNode(): ?string
    {
        return $this->ruleNode;
    }

    public function setRuleNode(string $ruleNode): self
    {
        $this->ruleNode = $ruleNode;
        return $this;
    }

    public function getRuleVnc(): ?string
    {
        return $this->ruleVnc;
    }

    public function setRuleVnc(string $ruleVnc): self
    {
        $this->ruleVnc = $ruleVnc;
        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(?string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getAccessKey(): ?string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): self
    {
        $this->accessKey = $accessKey;
        return $this;
    }

    public function getProcess(): ?int
    {
        return $this->process;
    }

    public function setProcess(int $process): self
    {
        $this->process = $process;
        return $this;
    }

    public function jsonSerialize()
    {
        if ($this->getUser()) {
            $user = $this->getUser()->getId();
        } else {
            $user = null;
        }

        if ($this->getIsAttribued()) {
            $isAttribued = $this->getIsAttribued()->getId();
        } else {
            $isAttribued = null;
        }

        return [
            'id' => $this->getId(),
            'taskArn' => $this->getTaskArn(),
            'isAttribued' => $isAttribued,
            'publicIp' => $this->getPublicIp(),
            'createdAt' => $this->getCreatedAt(),
            'updatedAt' => $this->getUpdatedAt(),
            'user' => $user,
            'containerKey' => $this->getContainerKey(),
            'status' => $this->getStatus(),
            'targetNode' => $this->getTargetNode(),
            'targetVnc' => $this->getTargetVnc(),
            'ruleNode' => $this->getRuleNode(),
            'ruleVnc' => $this->getRuleVnc(),
            'link' => $this->getLink(),
            'priority' => $this->getPriority(),
            'accessKey' => $this->getAccessKey(),
            'process' => $this->getProcess(),
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
