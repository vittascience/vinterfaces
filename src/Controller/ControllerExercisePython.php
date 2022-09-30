<?php

namespace Interfaces\Controller;

use Interfaces\Entity\Project;
use Interfaces\Entity\ExercisePython;



class ControllerExercisePython extends Controller
{
    public function __construct($entityManager, $user)
    {
        parent::__construct($entityManager, $user);
        $this->actions = array(
            'get_by_project' => function ($data) {
                $projectDeseralized = Project::jsonDeserialize($data['project']);
                if($projectDeseralized->getLink()){
                    $projectSynchronized = $this->entityManager->getRepository('Interfaces\Entity\Project')->findOneBy(array("link" => $projectDeseralized->getLink()));
                
                    return $projectSynchronized->getExercise();
                }
                return;
            },
            "update" => function () {
               
                /**
                  * This method when the user click on the save or update exercice button
                  */
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "updateNotRetrievedNotAuthenticated"];
                

                // bind incoming data and populate object to use
                $userId = intval($_SESSION['id']);
                $incomingExerciseData = $this->bindIncomingData($_POST['exercise']); 
                
                $projectId = $incomingExerciseData->project;
                $functionName = $incomingExerciseData->functionName;
                $secretWord = $incomingExerciseData->secretWord;
                $linkSolution = $incomingExerciseData->linkSolution;

                // create empty $errors array and check for errors
                $errors = [];
                if(empty($projectId)) $errors['projectIdInvalid'] = true;

                if(!empty($errors)){
                    echo json_encode(array('errors' => $errors));
                    exit;
                }

                $storedProjectExists = $this->entityManager
                    ->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array(
                        'user' => $userId,
                        'id' => $projectId
                ));

                if(!$storedProjectExists){
                    echo json_encode(array('errorType' => 'projectInvalid'));
                    exit;
                }

                if($storedProjectExists->getInterface() == 'python' && empty($functionName)) $errors['functionNameInvalid'] = true;
                if(!empty($errors)){
                    echo json_encode(array('errors' => $errors)); 
                    exit; 
                } 

                // get the exercise
                $storedExercise = $storedProjectExists->getExercise();
                
                if(!$storedExercise){
                    // create the exercise
                    $exercise = new ExercisePython($functionName);
                    $exercise->setSecretWord($secretWord);
                    $exercise->setLinkSolution($linkSolution);
                    $this->entityManager->persist($exercise);
                    $this->entityManager->flush();

                    // add the exercise to the interfaces_projects table
                    $storedProjectExists->setExercise($exercise);
                    $storedProjectExists->setIsExerciseCreator(true);
                    $this->entityManager->flush();
                    return $exercise;
                }

                // update the exercise
                $storedExercise->setFunctionName($incomingExerciseData->functionName);
                $storedExercise->setLinkSolution($incomingExerciseData->linkSolution);
                $storedExercise->setSecretWord($incomingExerciseData->secretWord);
                $this->entityManager->flush();

                return $storedExercise;
                
             }
        );
    }
    private function bindIncomingData($incomingData){
        $exercise = new \stdClass;

        $exercise->id = !empty($incominData['id']) ? intval($incomingData['id']) : 0;
        $exercise->secretWord = !empty($incomingData['secretWord']) ? htmlspecialchars(strip_tags(trim($incomingData['secretWord']))) : '';
        $exercise->functionName = !empty($incomingData['functionName']) ? htmlspecialchars(strip_tags(trim($incomingData['functionName']))) : '';
        $exercise->project = !empty($incomingData['project']) ? intval($incomingData['project']) : 0;
        $exercise->linkSolution = !empty($incomingData['linkSolution']) ? htmlspecialchars(strip_tags(trim($incomingData['linkSolution']))) : '';

        return $exercise;
    }

    public function action($action, $data = [], $async = false)
    {
        if ($async)
            echo (json_encode(call_user_func($this->actions[$action], $data)));
    }
   
}
