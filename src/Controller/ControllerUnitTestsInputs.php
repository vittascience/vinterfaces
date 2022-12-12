<?php

namespace Interfaces\Controller;

use Interfaces\Entity\UnitTestsInputs;
use Interfaces\Entity\UnitTests;

class ControllerUnitTestsInputs extends Controller
{
    public function __construct($className, $user)
    {
        parent::__construct($className, $user);
        $this->actions = array(
            'get_by_unittest' => function ($data) {
                $testDeseralized = UnitTests::jsonDeserialize($data['unitTest']);
                $testSynchronized = $this->entityManager->getRepository('Interfaces\Entity\UnitTests')
                    ->findBy(array("id" => $testDeseralized->getId()));
                return $this->entityManager->getRepository('Interfaces\Entity\UnitTestsInputs')
                    ->findBy(array("unitTest" => $testSynchronized));
            },
            "update" => function ($data) {
                $idTabToReturn = [];
                for ($i = 0; $i < count($data['iO']); $i++) {
                    $input = UnitTestsInputs::jsonDeserialize($data['iO'][$i]);
                    $input->setUnitTest(UnitTests::jsonDeserialize(json_decode($input->getUnitTest())));
                    $databaseInput = $this->entityManager->getRepository('Interfaces\Entity\UnitTestsInputs')->find($input->getId());
                    if ($databaseInput === null) {
                        $test = $this->entityManager->getRepository('Interfaces\Entity\UnitTests')
                            ->find(intval($input->getUnitTest()->getId()));
                        $input->setUnitTest($test);
                    } else {
                        $databaseInput->copy($input);
                        $input = $databaseInput;
                    }

                    $this->entityManager->persist($input);
                    $this->entityManager->flush();
                    $idTabToReturn[$i] = $input;
                }
                return $idTabToReturn;
            },
            "delete" => function ($data) {
                $databaseInput = $this->entityManager->getRepository('Interfaces\Entity\UnitTestsInputs')->find(intVal($data['iO']));
                $this->entityManager->remove($databaseInput);
                $this->entityManager->flush();
            }
        );
    }

    public function action($action, $data = [], $async = false)
    {
        if ($async)
            echo (json_encode(call_user_func($this->actions[$action], $data)));
    }
}
