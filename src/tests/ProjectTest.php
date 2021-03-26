<?php

namespace Interfaces\Tests;

use PHPUnit\Framework\TestCase;
use User\Entity\User;
use Interfaces\Entity\Project;
use Utils\TestConstants;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;

class ProjectTest extends TestCase
{
   public function testIdIsSet()
   {
      $user = new User();
      $user->setId(TestConstants::TEST_INTEGER); // right argument
      $this->assertEquals($user->getId(), 5);
      /*       $this->expectException(EntityDataIntegrityException::class);
      $user->setId(-1); // negative
      $user->setId(true); // boolean
      $user->setId(null); // null */
   }

   public function testUserIsSet()
   {
      $user = new User();
      $project = new Project();
      $project->setUser($user); // right argument
      $this->assertEquals($project->getUser(), $user);
      $this->expectException(EntityDataIntegrityException::class);
      $project->setUser(TestConstants::TEST_INTEGER); // integer
      $project->setUser(true); // boolean
      $project->setUser(null); // null
   }
   public function testNameIsSet()
   {
      $project = new Project();

      $acceptedName = 'aaaa';
      $nonAcceptedName = '';
      for ($i = 0; $i <= TestConstants::NAME_MAX_LENGTH; $i++) //add more than 20 characters 
         $nonAcceptedName .= 'a';

      $project->setName($acceptedName); // right argument
      $this->assertEquals($project->getName(), $acceptedName);
      $this->expectException(EntityDataIntegrityException::class);
      $project->setName(TestConstants::TEST_INTEGER); // integer
      $project->setName(true); // boolean
      $project->setName(null); // null
      $project->setName($nonAcceptedName); // more than 20 chars
   }

   public function testDescriptionIsSet()
   {
      $project = new Project();

      $acceptedDescription = 'aaaa';
      $nonAcceptedDescription = '';
      for ($i = 0; $i <= TestConstants::DESCRIPTION_MAX_LENGTH; $i++) //add more than 1000 characters 
         $nonAcceptedDescription .= 'a';

      $project->setDescription($acceptedDescription); // right value
      $this->assertEquals($project->getDescription(), $acceptedDescription);
      $project->setDescription(null); // null
      $project->setDescription($nonAcceptedDescription);
      $project->setDescription(TestConstants::TEST_INTEGER); // integer
   }

   public function testDateIsSet()
   {
      $project = new Project();
      $date = new \DateTime();
      $project->setDateUpdated($date->format('Y-m-d\TH:i:s.u'));
      $this->assertEquals($project->getDateUpdated(), $date);
      $project->setDateUpdated("2011-01-01T15:03:01.012345Z"); // should not be integer
   }

   public function testCodeIsSet()
   {
      $project = new Project();
      $project->setCode(TestConstants::TEST_CODE); // right argument
      $project->setCodeText(TestConstants::TEST_CODE_C); // right argument
      $this->assertEquals($project->getCode(), TestConstants::TEST_CODE);
      $this->assertEquals($project->getCodeText(), TestConstants::TEST_CODE_C);

      $this->expectException(EntityDataIntegrityException::class);

      $project->setCode(TestConstants::TEST_INTEGER); // integer
      $project->setCode(true); // boolean
      $project->setCode(null); // null


      $project->setCodeText(TestConstants::TEST_INTEGER); // integer
      $project->setCodeText(true); // boolean
      $project->setCodeText(null); // null
   }

   public function testCManuallyModifiedIsSet()
   {
      $project = new Project();
      $project->setCodeManuallyModified(true); // sould be bool
      $this->assertTrue($project->isCManuallyModified());
      $this->expectException(EntityDataIntegrityException::class);
      $project->setCodeManuallyModified(TestConstants::TEST_INTEGER); // should not be an integer
      $project->setCodeManuallyModified("test"); // should ne be a string
      $project->setCodeManuallyModified(null); // should not be null
   }

   public function testPublicIsSet()
   {
      $project = new Project();
      $project->setPublic(true); // should be bool
      $this->assertTrue($project->isPublic());
      $this->expectException(EntityDataIntegrityException::class);
      $project->setPublic(TestConstants::TEST_INTEGER); // should not be an integer
      $project->setPublic(TestConstants::TEST_STRING); // should ne be a string
      $project->setPublic(null); // should not be null
   }

   public function testLinkIsSet()
   {
      $project = new Project();
      $project->setLink(TestConstants::TEST_STRING); // should be a string
      $this->assertEquals($project->getLink(), 'test');
      $this->expectException(EntityDataIntegrityException::class);
      $project->setLink(TestConstants::TEST_INTEGER); // should not be an integer
      $project->setLink(true); // should ne be a boolean
      $project->setLink(null); // should not be null
   }

   public function testDeletedIsSet()
   {
      $project = new Project();
      $project->setDeleted(true); // should be bool
      $this->assertTrue($project->isDeleted());
      $this->expectException(EntityDataIntegrityException::class);
      $project->setDeleted(TestConstants::TEST_INTEGER); // should not be an integer
      $project->setDeleted("test"); // should not be a string
      $project->setDeleted(null); // should not be null
   }

   public function testCopyIsSet()
   {
      $project = new Project();
      $projectCopy = new Project();
      $project->setName(TestConstants::TEST_STRING);
      $project->setDescription('test123');
      $project->setDateUpdated("2011-01-01T15:03:01.012345Z");
      $projectCopy->copy($project); // after copying the projects must be equal
      $this->assertEquals($project, $projectCopy);
      $this->expectException(EntityOperatorException::class);
      $project->copy(null); // should not copy a null value
      $project->copy(TestConstants::TEST_INTEGER); // should not copy an integer
      $project->copy(TestConstants::TEST_STRING); // should not copy a string
      $project->copy(true); // should not copy a bool
   }

   /*    public function testjsonSerialize()
   {
      $project = new Project();
      $user = new User();
      $user->setId(TestConstants::TEST_INTEGER);
      $user->setFirstName('testFirstname');
      $user->setSurname('testSurname');
      $project->SetUser($user);
      //$user->setPrivateFlag(true);
      //test array
      $test = [
         'user' => $user->jsonSerialize(),
         'name' => 'Unamed',
         'description' => 'No description',
         'dateUpdated' => null,
         'code' => '',
         'codeText' => '',
         'codeManuallyModified' => false,
         'public' => false,
         'link' => null,
      ];
      $this->assertEquals($project->jsonSerialize(), $test);
   } */
}
