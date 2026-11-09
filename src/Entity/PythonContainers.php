<?php

namespace Interfaces\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use User\Entity\User;


#[ORM\Table(name: 'python_containers')]
#[ORM\Entity(repositoryClass: \Interfaces\Repository\PythonContainersRepository::class)]
class PythonContainers implements \JsonSerializable, \Utils\JsonDeserializer
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private $id;

    /**
     * @var string
     */
    #[ORM\Column(name: 'task_arn', type: 'string', length: 255, nullable: false)]
    private $taskArn;

    /**
     * @var User
     */
    #[ORM\JoinColumn(name: 'is_attribued', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OneToOne(targetEntity: \User\Entity\User::class)]
    private $isAttribued;

    /**
     * @var String
     */
    #[ORM\Column(name: 'access_key', type: 'string', length: 255, nullable: true)]
    private $accessKey;

    /**
     * @var string
     */
    #[ORM\Column(name: 'public_ip', type: 'string', length: 255, nullable: true)]
    private $publicIp;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'created_at', type: 'datetime', columnDefinition: 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP')]
    private $createdAt;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'updated_at', type: 'datetime', columnDefinition: 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP')]
    private $updatedAt;

    /**
     * @var User
     */
    #[ORM\JoinColumn(name: 'user', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OneToOne(targetEntity: \User\Entity\User::class)]
    private $user;

    /**
     * @var string
     */
    #[ORM\Column(name: 'container_key', type: 'string', length: 255, nullable: true)]
    private $containerKey;

    /**
     * @var string
     */
    #[ORM\Column(name: 'status', type: 'string', length: 255, nullable: false)]
    private $status;

    /**
     * @var string
     */
    #[ORM\Column(name: 'rule_node', type: 'string', length: 255, nullable: true)]
    private $ruleNode;

    /**
     * @var string
     */
    #[ORM\Column(name: 'rule_vnc', type: 'string', length: 255, nullable: true)]
    private $ruleVnc;

    /**
     * @var string
     */
    #[ORM\Column(name: 'target_node', type: 'string', length: 255, nullable: true)]
    private $targetNode;

    /**
     * @var string
     */
    #[ORM\Column(name: 'target_vnc', type: 'string', length: 255, nullable: true)]
    private $targetVnc;

    /**
     * @var string
     */
    #[ORM\Column(name: 'link', type: 'string', length: 7, nullable: true)]
    private $link;

    /**
     * @var string
     */
    #[ORM\Column(name: 'priority', type: 'string', length: 25, nullable: true)]
    private $priority;

    /**
     * @var string
     */
    #[ORM\Column(name: 'process', type: 'integer', length: 10, nullable: false)]
    private $process = 0;

    /**
     * @return Int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return String
     */
    public function getTaskArn(): ?string
    {
        return $this->taskArn;
    }

    /**
     * @param String $taskArn
     * @return PythonContainers
     */
    public function setTaskArn(string $taskArn): self
    {
        $this->taskArn = $taskArn;
        return $this;
    }

    /**
     * @return User
     */
    public function getIsAttribued(): ?User
    {
        return $this->isAttribued;
    }

    /**
     * @param User $isAttribued
     * @return PythonContainers
     */
    public function setisAttribued(User $isAttribued): self
    {
        $this->isAttribued = $isAttribued;
        return $this;
    }

    /**
     * @return String
     */
    public function getPublicIp(): ?string
    {
        return $this->publicIp;
    }

    /**
     * @param String $publicIp
     * @return PythonContainers
     */
    public function setPublicIp(string $publicIp): self
    {
        $this->publicIp = $publicIp;
        return $this;
    }

    /**
     * @return Datetime
     */
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param Datetime createdAt
     * @return PythonContainers
     */
    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Datetime
     */
    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param Datetime updatedAt
     * @return PythonContainers
     */
    public function setUpdatedAt(DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }
    /**
     * @param User $id
     */
    public function setUser(?User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return String
     */
    public function getContainerKey(): ?string
    {
        return $this->containerKey;
    }

    /**
     * @param String $containerKey
     * @return PythonContainers
     */
    public function setContainerKey(string $containerKey): self
    {
        $this->containerKey = $containerKey;
        return $this;
    }

    /**
     * @return String
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param String $status
     * @return PythonContainers
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return String
     */
    public function getTargetNode(): ?string
    {
        return $this->targetNode;
    }

    /**
     * @param String $targetNode
     * @return PythonContainers
     */
    public function setTargetNode(string $targetNode): self
    {
        $this->targetNode = $targetNode;
        return $this;
    }
    /**
     * @return String
     */
    public function getTargetVnc(): ?string
    {
        return $this->targetVnc;
    }

    /**
     * @param String $targetVnc
     * @return PythonContainers
     */
    public function setTargetVnc(string $targetVnc): self
    {
        $this->targetVnc = $targetVnc;
        return $this;
    }

    /**
     * @return String
     */
    public function getRuleNode(): ?string
    {
        return $this->ruleNode;
    }

    /**
     * @param String $ruleNode
     * @return PythonContainers
     */
    public function setRuleNode(string $ruleNode): self
    {
        $this->ruleNode = $ruleNode;
        return $this;
    }

    /**
     * @return String
     */
    public function getRuleVnc(): ?string
    {
        return $this->ruleVnc;
    }

    /**
     * @param String $ruleVnc
     * @return PythonContainers
     */
    public function setRuleVnc(string $ruleVnc): self
    {
        $this->ruleVnc = $ruleVnc;
        return $this;
    }


    /**
     * @return String
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * @param String $link
     * @return PythonContainers
     */
    public function setLink(string $link): self
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @return Interger
     */
    public function getPriority(): ?string
    {
        return $this->priority;
    }

    /**
     * @param Interger $priority
     * @return PythonContainers
     */
    public function setPriority(?string $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return String
     */
    public function getAccessKey(): ?string
    {
        return $this->accessKey;
    }

    /**
     * @param String $userIp
     * @return PythonContainers
     */
    public function setAccessKey(string $accessKey): self
    {
        $this->accessKey = $accessKey;
        return $this;
    }

    /**
     * @return Int
     */
    public function getProcess(): ?int
    {
        return $this->process;
    }

    /**
     * @param Int $process
     * @return PythonContainers
     */
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
