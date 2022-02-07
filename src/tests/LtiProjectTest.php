<?php

namespace Interfaces\Tests;

use User\Entity\User;
use PHPUnit\Framework\TestCase;
use Interfaces\Entity\LtiProject;
use Utils\Exceptions\EntityDataIntegrityException;

class LtiProjectTest extends TestCase{
    private $litProject;

    public function setUp():void{
        $this->ltiProject = new LtiProject();
    }

    public function tearDown():void{
        $this->ltiProject = null;
    }

    public function testGetIdIsNullByDefault(){
        $this->assertNull($this->ltiProject->getId());
    }

    /** @dataProvider provideFakeIds */
    public function testGetIdReturnsId($providedValue){
        $this->assertNull($this->ltiProject->getId());
        
        // declare a fake setter for id
        $fakeSetIdClosureDeclaration = function() use($providedValue){
            return $this->id = $providedValue;
        };

        // bind the fake setter to the object
        $fakeSetIdClosureExecution = $fakeSetIdClosureDeclaration->bindTo(
            $this->ltiProject,
            LtiProject::class
        );

        // execute the fake setter
        $fakeSetIdClosureExecution();
       
        $this->assertEquals($providedValue, $this->ltiProject->getId());
    }

    public function testGetUserReturnAnInstanceOfUserClass(){
        // create mock of user
        $mockedUser = $this->createMock(User::class);

        // create fake setter function for user
        $createFakeSetUserClosureDeclaration = function() use ($mockedUser) {
            return $this->user = $mockedUser;
        };

        // execute() the fake setter function
        $executeFakeSetUserClosureExecution = \Closure::bind(
            $createFakeSetUserClosureDeclaration,
            $this->ltiProject,
            LtiProject::class 
        );

        // execute the fake setter
        $executeFakeSetUserClosureExecution();

        $this->assertInstanceOf(User::class,$this->ltiProject->getUser());
    }

    public function testGetProjectLinkIsNotNullByDefault(){
        $this->assertNotNull($this->ltiProject->getUserProjectLink());
        $this->assertSame('', $this->ltiProject->getUserProjectLink());
    }

    /** @dataProvider provideNonStringValues */
    public function testSetProjectLinkRejectNonStringValue($providedValue){

        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiProject->setUserProjectLink($providedValue);

    }

    /** @dataProvider provideStringUserProjectLinks */
    public function testSetProjectLinkAcceptsStringValue($providedStringValue){
        $this->assertEquals('',$this->ltiProject->getUserProjectLink());

        $this->ltiProject->setUserProjectLink($providedStringValue);
        $this->assertEquals($providedStringValue, $this->ltiProject->getUserProjectLink());
    }

    public function testGetLtiCourseIdIsNotNullByDefault(){
        $this->assertNotNull($this->ltiProject->getLtiCourseId());
        $this->assertSame('', $this->ltiProject->getLtiCourseId());
    }

    /** @dataProvider provideFakeIds */
    public function testGetLtiCourseIdReturnsIds($providedValue){

        $this->assertEquals('',$this->ltiProject->getLtiCourseId());

        // create fake setter
        $fakeLtiCourseIdSetterDeclaration = function() use ($providedValue){
            return $this->ltiCourseId = $providedValue;
        };

        // bind fake setter to object
        $fakeLtiCourseIdSetterExecution = $fakeLtiCourseIdSetterDeclaration->bindTo($this->ltiProject,LtiProject::class);

        // run fake setter
        $fakeLtiCourseIdSetterExecution();

        $this->assertEquals($providedValue, $this->ltiProject->getLtiCourseId());
    }

    /** @dataProvider provideNonStringValues */
    public function testSetLtiCourseIdRejectNonStringValue($providedValue){

        $this->assertEquals('',$this->ltiProject->getLtiCourseId());

        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiProject->setLtiCourseId($providedValue);
    }

    /** @dataProvider provideStringIds */
    public function testSetLtiCourseIdAcceptsValidValue($providedStringValue){
        $this->assertSame('', $this->ltiProject->getLtiCourseId());

        $this->ltiProject->setLtiCourseId($providedStringValue);
        $this->assertSame($providedStringValue, $this->ltiProject->getLtiCourseId());
    }

    public function testGetLtiResourceLinkIdIsNotNull(){
        $this->assertNotNull($this->ltiProject->getLtiResourceLinkId());
    }

    /** @dataProvider provideStringIds */
    public function testGetLtiResourceLinkIdReturnsValue($providedValue){

        // declare fake setter
        $fakeLtiResourceLinkIdSetterDeclaration = function() use ($providedValue){
            return $this->ltiResourceLinkId = $providedValue;
        };

        // bind fake setter
        $fakeLtiResourceLinkIdExecution = \Closure::bind(
            $fakeLtiResourceLinkIdSetterDeclaration,
            $this->ltiProject,
            LtiProject::class
        );

        // execute fake setter
        $fakeLtiResourceLinkIdExecution();

        $this->assertSame($providedValue, $this->ltiProject->getLtiResourceLinkId());
    }

    /** @dataProvider provideNonStringValues */
    public function testSetLtiResourceLinkIdRejectInvalidValue($providedValue){
        $this->assertSame('', $this->ltiProject->getLtiResourceLinkId());

        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiProject->setLtiResourceLinkId($providedValue);
    }

    /** @dataProvider provideStringIds */
    public function testSetLtiResourceLinkIdAcceptsStringValue($providedStringValue){
        $this->assertSame('', $this->ltiProject->getLtiResourceLinkId());

        $this->ltiProject->setLtiResourceLinkId($providedStringValue);
        $this->assertEquals($providedStringValue, $this->ltiProject->getLtiResourceLinkId());
    }

    public function testGetIsSubmittedIsNotNullAndIsFalseByDefault(){
        $this->assertNotNull($this->ltiProject->getIsSubmitted());
        $this->assertFalse($this->ltiProject->getIsSubmitted());
    }

    /** @dataProvider provideBooleanValues */
    public function testGetIsSubmittedReturnsValue($providedValue){

        // fake isSubmitted setter
        $fakeIsSubmittedSetterDeclaration = function() use ($providedValue){
            return $this->isSubmitted = $providedValue;
        };

        // bind fake setter to object
        $fakeIsSubmittedSetterExecution = $fakeIsSubmittedSetterDeclaration->bindTo(
            $this->ltiProject,
            LtiProject::class
        );

        // execute fake setter
        $fakeIsSubmittedSetterExecution();

        $this->assertIsBool($this->ltiProject->getIsSubmitted());
        $this->assertEquals($providedValue, $this->ltiProject->getIsSubmitted());
    }

    /** @dataProvider provideNonBooleanValues */
    public function testSetIsSubmittedRejectsInvalidValue($providedValue){

        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiProject->setIsSubmitted($providedValue);
    }

    /** @dataProvider provideBooleanValues */
    public function testSetIsSubmittedAcceptsValidValue($providedValue){
        $this->assertFalse($this->ltiProject->getIsSubmitted());

        $this->ltiProject->setIsSubmitted($providedValue);
        $this->assertEquals($providedValue, $this->ltiProject->getIsSubmitted());
    }

    /** dataProvider for testGetIdReturnsId */
    public function provideFakeIds(){
        return array(
            array(1),
            array('1'),
            array(1000),
        );
    }
    /** @dataProvider provideNonObjectValues */
    public function testSetUserRejectNonUserObjectValue($providedValue){
        $this->assertNull($this->ltiProject->getUser());

        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiProject->setUser($providedValue);
    }

    /** @dataProvider provideUserObjectValues */
    public function testSetUserAcceptsUserObjectValue($providedUserObjectValue){
        $this->assertNull($this->ltiProject->getUser());

        $this->ltiProject->setUser($providedUserObjectValue);
        $this->assertEquals($providedUserObjectValue,$this->ltiProject->getUser());

    }

    /** dataProvider for testSetRejectNonObjectValues */
    public function provideNonObjectValues(){
        return array(
            array('1'),
            array(1),
            array(['some value']),
        );
    }

    /** dataProvider for testSetUserAcceptsUserObjectValue */
    public function provideUserObjectValues(){
        $user1 = new User;
        $user1->setPseudo('user1');
        $user2 = new User;
        $user2->setPseudo('user2');
        $user3 = new User;
        $user3->setPseudo('user3');

        return array(
            array($user1),
            array($user2),
            array($user3),
        );
    }

    /** 
     * dataProvider for 
     * => testSetProjectLinkRejectNonStringValue
     * => testSetLtiCourseIdRejectNonStringValue
     * => testSetLtiResourceLinkIdRejectInvalidValue
     *  */
    public function provideNonStringValues(){
        return array(
            array(3),
            array(new \stdClass),
            array(true),
            array(['',1])
        );
    }

    /** dataProvider for testGetClientIdAcceptsStringValue */
    public function provideStringUserProjectLinks(){
        return array(
            array('6d30c1b5b7da5'),
            array('5d30c1b5b7da5'),
            array('4d30c1b5b7da5'),
        );
    }

    /** 
     * dataProvider for 
     * => testSetLtiCourseIdAcceptsValidValue 
     * => testSetLtiResourceLinkIdAcceptsStringValue
     * */
    public function provideStringIds(){
        return array(
            array('3'),
            array('1000'),
            array('658')
        );
    }

    /** 
     * dataProvider for 
     * => testGetIsSubmittedReturnsValue
     * => testSetIsSubmittedAcceptsValidValue
     */
    public function provideBooleanValues(){
        return array(
            array(true),
            array(false),
        );
    }

    /** dataProvider for testSetIsSubmittedRejectsInvalidValue */
    public function provideNonBooleanValues(){
        return array(
            array(3),
            array(new \stdClass),
            array('some data'),
            array(['',1])
        );
    }

}