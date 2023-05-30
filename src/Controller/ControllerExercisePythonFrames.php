<?php

namespace Interfaces\Controller;

use Interfaces\Entity\Project;
use Interfaces\Entity\ExercisePythonFrames;

class ControllerExercisePythonFrames extends Controller
{
    public function __construct($entityManager, $user)
    {
        parent::__construct($entityManager, $user);
        $this->actions = array(
            'get_exercise_frames' => function () {
                // Clean incoming variables
                $incoming_data = json_decode(file_get_contents('php://input'));
                $exercise_id = $incoming_data->exerciseId;
                // Verify Errors
                // Process Data
                $frames = $this->entityManager->getRepository('Interfaces\Entity\ExercisePythonFrames')->findBy(['exercise' => $exercise_id]);
                // Sort Frames by Modules ID and Frame Count
                usort($frames, function($a, $b) {
                    if ($a->getComponent() == $b->getComponent()) {
                        if ($a->getFrame() > $b->getFrame()) {
                            return 1;
                        }
                    }
                    return $a->getComponent() > $b->getComponent();
                });
                $tmp_frames = [];
                foreach($frames as $frame) {
                    array_push($tmp_frames, $frame->jsonSerialize());
                }
                $test = json_encode($tmp_frames);
                echo $test;
                exit;
            },
            'create_exercise_frames' => function() {

                /**
                 * This method when the user click on the save or modify exercice button 
                 * in order to save the exercise frames
                 */
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "getStudentDataNotRetrievedNotAuthenticated"];

                $userId = intval($_SESSION['id']);

                // get incoming frames
                // $incomingData = json_decode(file_get_contents('php://input'));
                $incomingData = json_decode($_POST['frames']);

                $exerciseId = !empty($incomingData->exerciseId) ? intval($incomingData->exerciseId) : 0;

                // get the exercise from python_exercices table
                $exercise = $this->entityManager
                    ->getRepository('Interfaces\Entity\ExercisePython')
                    ->find($exerciseId);

                $project = $this->entityManager->getRepository('Interfaces\Entity\Project')->findOneBy(array(
                    'user' => $userId,
                    'exercise' => $exerciseId,
                    'isExerciseCreator'=> true
                ));
                if(!$project) {
                    echo json_encode(array('errorType' => 'notProjectOwnerCanNotUpdateExercise'));
                    exit;
                }
                
                $sanitizedFrames = [];

                foreach($incomingData->optimizedFrames as $incomingFrame) {
                    // sanitize frame data
                    $frame = !empty($incomingFrame->frame) ? intval($incomingFrame->frame) : 0;
                    $componentId = !empty($incomingFrame->id_component) ? htmlspecialchars(strip_tags(trim($incomingFrame->id_component))) : '';
                    $value = !empty($incomingFrame->value) ? $incomingFrame->value : 0;

                    array_push($sanitizedFrames, array(
                        'exerciceId' => $exerciseId,
                        'frame' => $frame,
                        'componentId' => $componentId,
                        'value' => json_encode($value)                      
                    ));

                }

                // get the exercise frames from python_exercise_frames table if any
                $exerciseFramesFound = $this->entityManager
                    ->getRepository('Interfaces\Entity\ExercisePythonFrames')
                    ->findBy(array('exercise'=> $exerciseId));

                // some exercise frames were found, remove them
                if($exerciseFramesFound){
                    
                    foreach($exerciseFramesFound as $exerciseFrameFound){
                        $this->entityManager->remove($exerciseFrameFound);
                    }
                    $this->entityManager->flush();
                }

                foreach($sanitizedFrames as $sanitizedFrame){
                    $exercise_frame = new ExercisePythonFrames();
                    $exercise_frame->setExercise($exercise);
                    $exercise_frame->setFrame($sanitizedFrame['frame']);
                    $exercise_frame->setComponent($sanitizedFrame['componentId']);
                    $exercise_frame->setValue($sanitizedFrame['value']);

                    $this->entityManager->persist($exercise_frame);
                }
                // Save data to DB
                
                $this->entityManager->flush();
                echo json_encode(array('msg' => "done", 'exercise' => $exercise_frame));
                exit;
            }
        );
    }
}
