<?php

namespace Interfaces\Controller;

use Interfaces\Entity\UnitTestsOutputs;
use Interfaces\Entity\UnitTests;

class ControllerUnitTestsOutputs extends Controller
{
    public function __construct($className, $user)
    {
        parent::__construct($className, $user);
        $this->actions = array(
            'get_by_unittest' => function ($data) {
                $testDeseralized = UnitTests::jsonDeserialize($data['unitTest']);
                $testSynchronized = $this->entityManager->getRepository('Interfaces\Entity\UnitTests')
                    ->findBy(array("id" => $testDeseralized->getId()));
                return $this->entityManager->getRepository('Interfaces\Entity\UnitTestsOutputs')
                    ->findBy(array("unitTest" => $testSynchronized));
            },
            "update" => function ($data) {
                $idTabToReturn = [];
                for ($i = 0; $i < count($data['iO']); $i++) {
                    $output = UnitTestsOutputs::jsonDeserialize($data['iO'][$i]);
                    $output->setUnitTest(UnitTests::jsonDeserialize(json_decode($output->getUnitTest())));
                    $databaseOutput = $this->entityManager->getRepository('Interfaces\Entity\UnitTestsOutputs')->find($output->getId());
                    if ($databaseOutput === null) {
                        $test = $this->entityManager->getRepository('Interfaces\Entity\UnitTests')
                            ->find(intval($output->getUnitTest()->getId()));
                        $output->setUnitTest($test);
                    } else {
                        $databaseOutput->copy($output);
                        $output = $databaseOutput;
                    }

                    $this->entityManager->persist($output);
                    $this->entityManager->flush();
                    $idTabToReturn[$i] = $output;

                }
                return $idTabToReturn;
            },
            "delete" => function ($data) {
                for ($i = 0; $i < count($data['iO']); $i++) {
                    $databaseOutput = $this->entityManager->getRepository(UnitTestsOutputs::class)->find($data['iO'][$i]);
                    if($databaseOutput){
                        $this->entityManager->remove($databaseOutput);
                    }
                }
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
