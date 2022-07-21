<?php

namespace Interfaces\Controller;

use Interfaces\Entity\UnitTests;
use Interfaces\Entity\ExercisePython;

class ControllerUnitTests extends Controller
{
    public function __construct($entityManager, $user)
    {
        parent::__construct($entityManager, $user);
        $this->actions = array(
            'get_by_exercise' => function ($data) {
                $exerciseDeseralized = ExercisePython::jsonDeserialize($data['exercise']);
                $exerciseSynchronized = $this->entityManager->getRepository('Interfaces\Entity\ExercisePython')
                    ->findBy(array("id" => $exerciseDeseralized->getId()));
                return $this->entityManager->getRepository('Interfaces\Entity\UnitTests')
                    ->findBy(array("exercise" => $exerciseSynchronized));
            },
            "update" => function ($data) {
                $unitTest = UnitTests::jsonDeserialize($data['test']);
                $unitTest->setExercise(ExercisePython::jsonDeserialize($unitTest->getExercise()));

                $databaseUnitTest = $this->entityManager->getRepository('Interfaces\Entity\UnitTests')->find($unitTest->getId());
                if ($databaseUnitTest === null) {
                    $exercise = $this->entityManager->getRepository('Interfaces\Entity\ExercisePython')
                        ->find(intval($unitTest->getExercise()->getId()));
                    $unitTest->setExercise($exercise);
                } else {
                    $databaseUnitTest->copy($unitTest);
                    $unitTest = $databaseUnitTest;
                }
                $this->entityManager->persist($unitTest);
                $this->entityManager->flush();

                return $unitTest;
            },
            "delete" => function ($data) {
                $databaseInput = $this->entityManager->getRepository('Interfaces\Entity\UnitTests')->find($data['test']);
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
