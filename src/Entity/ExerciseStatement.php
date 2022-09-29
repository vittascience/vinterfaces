<?php

namespace Interfaces\Entity;

use User\Entity\User;
use Utils\MetaDataMatcher;
use Interfaces\Entity\Project;
use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityOperatorException;
use Doctrine\Common\Collections\ArrayCollection;
use Utils\Exceptions\EntityDataIntegrityException;

/**
 * @ORM\Entity(repositoryClass="Interfaces\Repository\ExerciseStatementRepository")
 * @ORM\Table(name="interfaces_exercise_statements")
 */
class ExerciseStatement implements \JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(name="statement_content", type="text", nullable=false)
     */
    private $statementContent;

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of statementContent
     *
     * @return  string
     */
    public function getStatementContent()
    {
        return $this->statementContent;
    }

    /**
     * Set the value of statementContent
     *
     * @param  string  $statementContent
     *
     * @return  self
     */
    public function setStatementContent($statementContent)
    {
        if(!is_string($statementContent)){
            throw new EntityDataIntegrityException("The statement content has to be a string");
        }

        $this->statementContent = $statementContent;

        return $this;
    }

    /**
     * convert doctrine object into array
     *
     * @return  array  
     */
    public function jsonSerialize()
    {
        return array(
            'id' => $this->getId(),
            'statementContent' => $this->getStatementContent()
        );
    }
}
