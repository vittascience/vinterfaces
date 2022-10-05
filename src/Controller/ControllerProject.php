<?php

namespace Interfaces\Controller;

use User\Entity\User;
use GuzzleHttp\Client;
use User\Entity\Regular;
use Interfaces\Entity\Project;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Interfaces\Entity\LtiProject;
use Interfaces\Entity\ExerciseStatement;
use Interfaces\Entity\UnitTests;
use Interfaces\Entity\ExercisePython;
use Interfaces\Entity\UnitTestsInputs;
use Interfaces\Entity\UnitTestsOutputs;
use Interfaces\Entity\ExercisePythonFrames;

class ControllerProject extends Controller
{
    public function __construct($entityManager, $user)
    {
        parent::__construct($entityManager, $user);
        $this->actions = array(
            'get_all' => function ($data) {
                return $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findBy(array("deleted" => false, "interface" => $data['interface']));
            },
            'get_all_public' => function ($data) {
                return $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findBy(array("public" => true, "deleted" => false, "interface" => $data['interface']));
            },
            'get_by_link' => function ($data) {
                $link = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data['link']);
                return $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array("link" => $link, "deleted" => false));
            },
            'get_by_user' => function ($data) {
                return $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findBy(array("user" => $this->user['id'], "deleted" => false, "interface" => $data['interface']));
            },
            'generate_link' => function ($data) {
                $user = $this->entityManager->getRepository('User\Entity\User')
                    ->findOneBy(array("id" => $this->user['id']));
                $project = new Project($data['name'], $data['description']);
                $project->setUser($user);
                $project->setDateUpdated();
                $project->setCode($data['code']);
                $project->setCodeText($data['codeText']);
                $project->setCodeManuallyModified($data['codeManuallyModified']);
                $project->setPublic($data['public']);
                $project->setLink(uniqid());
                $project->setInterface($data['interface']);
                $this->entityManager->persist($project);
                $this->entityManager->flush();
                return $project->getLink();
            },
            'add' => function ($data) {
                $user = $this->entityManager->getRepository('User\Entity\User')
                    ->findOneBy(array("id" => $this->user['id']));
                $project = new Project($data['name'], $data['description']);
                $project->setUser($user);
                $project->setDateUpdated();
                $project->setCode($data['code']);
                $project->setCodeText($data['codeText']);
                $project->setCodeManuallyModified($data['codeManuallyModified']);
                $project->setPublic($data['public']);
                $project->setLink(uniqid());
                $project->setInterface($data['interface']);
                $project->setSharedStatus($data['sharedStatus'] ?? 0);
                if (isset($data['activitySolve'])) {
                    $project->setActivitySolve(true);
                }
                $this->entityManager->persist($project);
                $this->entityManager->flush();
                return $project; //synchronized
            },
            'update_my_project' => function ($data) {

                /**
                 * RTC update
                 */

                $projectJSON = json_decode($_POST['project']);
                $requesterId = !empty($_SESSION['id']) ? intval($_SESSION['id']) : null;
                $requesterLink = !empty($_POST['requesterLink']) ? $_POST['requesterLink'] : null;
                if (empty($requesterLink)) return ["errorType" => "no requester link"];
                $project = $this->entityManager->getRepository(Project::class)->findOneBy(array("link" => $projectJSON->link));
                if ($requesterId != null) {
                    $requesterRegular = $this->entityManager->getRepository(Regular::class)->findOneBy(["user" => $requesterId]);
                }
                $projectOwner = $project->getUser();
                $projectSharedUsers = $project->getSharedUsers();
                $projectSharedStatus = $project->getSharedStatus();
                $userChanged = [false, null, null];

                if ($projectSharedUsers) {
                    $unserializedSharedUsers = @unserialize($projectSharedUsers);
                    if (!$unserializedSharedUsers) {
                        $unserializedSharedUsers = [];
                    }
                }

                $canUpdateProject = false;
                if ($projectOwner->getId() == $requesterId) {
                    $canUpdateProject = true;
                } else {
                    if ($unserializedSharedUsers) {
                        foreach ($unserializedSharedUsers as $key => $sharedUser) {
                            if ($sharedUser['userId'] == $requesterId) {
                                if ($sharedUser['right'] == 2) {
                                    $canUpdateProject = true;
                                    break;
                                }
                            } else if ($requesterRegular->getEmail() == $sharedUser['userId']) {
                                if ($sharedUser['right'] == 2) {
                                    $sharedUser['userId'] = $requesterRegular->getUser()->getId();
                                    $canUpdateProject = true;
                                    $userChanged = [true, $key, $sharedUser['userId']];
                                    break;
                                }
                            }
                        }
                    }
                }

                if ($userChanged[0]) {
                    $unserializedSharedUsers[$userChanged[1]]['userId'] = $userChanged[2];
                    $project->setSharedUsers(serialize($unserializedSharedUsers));
                }

                if ($projectSharedStatus == 2) {
                    $canUpdateProject = true;
                }

                if ($canUpdateProject || $projectSharedStatus) {
                    $project->setDateUpdated();
                    $project->setCode($projectJSON->code);
                    $project->setName($projectJSON->name);
                    $project->setDescription($projectJSON->description);
                    $project->setCodeText($projectJSON->codeText);
                    $project->setCodeManuallyModified($projectJSON->codeManuallyModified);
                    $project->setPublic($projectJSON->public);
                    $this->entityManager->persist($project);
                    $this->entityManager->flush();
                    return $project;
                } else {
                    return ['status' => false, 'message' => "Vous n'avez pas le droit de modifier ce programme"];
                }
            },
            'toggle_public' => function ($data) {
                $Project = $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array("link" => $data['link']));
                $Project->setPublic($data['isPublic']);
                $this->entityManager->persist($Project);
                $this->entityManager->flush();
                return $Project;
            },
            'delete_project' => function ($data) {
                $project = $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array("link" => $data['link']));
                $toReturn = array("name" => $project->getName(), "link" => $project->getLink());
                $this->entityManager->remove($project);
                $this->entityManager->flush();
                return $toReturn;
            },
            'duplicate' => function () {

                /**
                 * This method when the user click on the save or modify exercice button 
                 * in order to save the exercise frames
                 */
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "NotAuthenticated"];

                // bind data
                $userId = intval($_SESSION['id']);
                $projectLink = !empty($_POST['link']) ? htmlspecialchars(strip_tags(trim($_POST['link']))) : '';
                $name = !empty($_POST['name']) ? htmlspecialchars(strip_tags(trim($_POST['name']))) : '';
                $description = !empty($_POST['description']) ? htmlspecialchars(strip_tags(trim($_POST['description']))) : '';
                $isPublic = $_POST['isPublic'] == 'true' ? true : false;

                // get data from db
                $user = $this->entityManager->getRepository(User::class)->find($userId);
                $project = $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array("link" => $projectLink));

                // set current code and overide it if interface is Ai
                $currentCode = $project->getCode();
                if ($project->getInterface() == 'ai') {
                    $currentCode = $this->getCodeFromAiInterface($project);
                }

                // set additional variables if we have incoming data set or use default values
                $projectName = $name ?? $project->getName();
                $projectDescription = $description ?? $project->getDescription();
                $projectIsPublic = $isPublic;


                $newProject = new Project($projectName, $projectDescription);
                $projectDuplicated = $this->getDuplicatedProject($project, $user, $newProject);

                $projectDuplicated->setPublic($projectIsPublic);
                $projectDuplicated->setCode($currentCode);

                $this->entityManager->persist($projectDuplicated);
                $this->entityManager->flush();

                // we have an exercise, duplicate it creating a new new exercise linked to the dupliacetd project
                if ($project->getExercise()) {

                    $duplicatedExercise = new \stdClass;

                    if ($project->getInterface() !== 'python') {
                        $duplicatedExercise = $this->duplicateAndAssignRelatedExercicesAndFrames($project);
                    } else {
                        $duplicatedExercise = $this->duplicateAndAssignRelatedExercicesAndTests($project);
                    }

                    if ($duplicatedExercise instanceof ExercisePython) {
                        $projectDuplicated->setExercise($duplicatedExercise);
                        $projectDuplicated->setIsExerciseCreator(true);
                    }
                }

                if ($project->getExerciseStatement()) {
                    // create exercise statement object
                    $exerciseStatementToSave = new ExerciseStatement;
                    $exerciseStatementToSave->setStatementContent($project->getExerciseStatement()->getStatementContent());
                    $this->entityManager->flush();
                    
                    $projectDuplicated->setExerciseStatement($exerciseStatementToSave);
                    $projectDuplicated->setIsExerciseStatementCreator(true);
                }
                
                $this->entityManager->flush();
                return $projectDuplicated;
            },
            'get_all_user_projects' => function ($data) {
                // To change
                if ($data['user']) {
                    $idUserToFetch = $data['user'];
                } else {
                    $idUserToFetch = $this->user['id'];
                }
                $userFetched = $this->entityManager->getRepository('User\Entity\User')
                    ->findOneBy(array("id" => $idUserToFetch));
                if ($userFetched === null) {
                    return [];
                } else {
                    if ($data['user']) {
                        return $this->entityManager->getRepository('Interfaces\Entity\Project')
                            ->findBy(array("deleted" => false, "user" => $userFetched, "public" => true, "interface" => $data['interface']));
                    } else {
                        return $this->entityManager->getRepository('Interfaces\Entity\Project')
                            ->findBy(array("deleted" => false, "user" => $userFetched, "interface" => $data['interface']));
                    }
                }
            },
            'is_autocorrected' => function ($data) {
                $project = $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array("link" => $data['link']));
                $exercise = $this->entityManager->getRepository('Interfaces\Entity\ExercisePython')
                    ->findOneBy(array("project" => $project->getId()));
                if ($exercise) {
                    return true;
                }
                return false;
            },
            'lti_duplicate_project_for_student' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "ltiDuplicateProjectForStudentNotRetrievedNotAuthenticated"];

                // bind and sanitize incoming data
                $userId = intval($_SESSION['id']);
                $ltiCourseId = !empty($_POST['course_id']) ? $_POST['course_id'] : null;
                $ltiResourceLinkId = !empty($_POST['resource_link_id']) ? $_POST['resource_link_id'] : null;
                $projectLink = !empty($_POST['link']) ? htmlspecialchars(strip_tags(trim($_POST['link']))) : '';

                // initialize empty $errors array
                $errors = [];
                //if(empty($ltiCourseId)) $errors['ltiCourseIdInvalid'] = true;
                if (empty($ltiResourceLinkId)) $errors['resourceLinkIdInvalid'] = true;
                if (empty($projectLink)) $errors['projectLinkInvalid'] = true;

                // some errors found, return them
                if (!empty($errors)) {
                    return array('errors' => $errors);
                }

                // get the user
                $user = $this->entityManager
                    ->getRepository(User::class)->find($userId);

                // get the project                        
                $project = $this->entityManager
                    ->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array("link" => $projectLink));

                // the project does not exists, return an error
                if (!$project) {
                    return array('errorType' => 'projectNotFoundWithProvidedLink');
                }

                //set up defaults params( without $ltiCourseIs as it is optional)
                $queryParams = array(
                    'user' => $user->getId(),
                    'ltiResourceLinkId' => $ltiResourceLinkId,
                    'isSubmitted' => 0
                );

                if ($ltiCourseId) {
                    $queryParams['ltiCourseId'] = $ltiCourseId;
                }

                // get the lti project from interfaces_lti_project table
                $ltiProjectNotAlreadySubmitted = $this->entityManager
                    ->getRepository(LtiProject::class)
                    ->findOneBy($queryParams);

                // no reference of this project in ltiProject ($user + $courseId + $ltiResourceId do not exists)
                if (!$ltiProjectNotAlreadySubmitted) {

                    $newProject = new Project(
                        $project->getName(),
                        $project->getDescription()
                    );
                    $projectDuplicated = $this->getDuplicatedProject($project, $user, $newProject);
                    // // we duplicate the project for this user and save it

                    // $projectDuplicated->setUser($user);
                    // $projectDuplicated->setDateUpdated();
                    // $projectDuplicated->setCode($project->getCode());
                    // $projectDuplicated->setCodeText($project->getCodeText());
                    // $projectDuplicated->setCodeManuallyModified($project->isCManuallyModified());
                    // $projectDuplicated->setPublic($project->isPublic());
                    // $projectDuplicated->setLink(uniqid());
                    // $projectDuplicated->setInterface($project->getInterface());

                    // set exercise 
                    if ($project->getExercise()) {
                        $projectDuplicated->setExercise($project->getExercise());
                        //$projectDuplicated->setIsExerciseCreator(false);
                    }
                    if ($project->getExerciseStatement()) {
                        $projectDuplicated->setExerciseStatement($project->getExerciseStatement());
                        //$projectDuplicated->setIsExerciseStatementCreator(false);
                    }

                    $this->entityManager->persist($projectDuplicated);
                    $this->entityManager->flush();

                    /* if($project->getInterface() == 'python'){
                        // save Exercise and related unit tests
                        $success = $this->assignRelatedExercicesAndTestsToStudent($project,$projectDuplicated);
                    } else {
                        // save Exercise and related unit tests
                        $success =  $this->assignRelatedExercicesAndFramesToStudent($project,$projectDuplicated);
                    }
                   
                    if(!$success){
                        return array('error'=> "ExercisesAndUnitTestsNotSavedProperly");
                    }  
                    */
                    // we create a ltiProject entry in interfaces_lti_projects and save it
                    $ltiProject = new LtiProject();
                    $ltiProject->setUser($user);
                    $ltiProject->setUserProjectLink($projectDuplicated->getLink());
                    $ltiProject->setLtiResourceLinkId($ltiResourceLinkId);
                    $ltiProject->setIsSubmitted(false);
                    if ($ltiCourseId) {
                        $ltiProject->setLtiCourseId($ltiCourseId);
                    }

                    $this->entityManager->persist($ltiProject);
                    $this->entityManager->flush();

                    // save data in session
                    $_SESSION['lti_project_id'] = $ltiProject->getId();
                    return  $projectDuplicated->jsonSerialize();
                }

                $userProject = $this->entityManager
                    ->getRepository(Project::class)
                    ->findOneByLink($ltiProjectNotAlreadySubmitted->getUserProjectLink());

                if (!$userProject) {
                    return array('errorType' => "userProjectNotFound");
                }

                // save data in session
                $_SESSION['lti_project_id'] = $ltiProjectNotAlreadySubmitted->getId();
                return $userProject;
            },
            'lti_student_submit_project' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "ltiDuplicateTeacherProjectNotRetrievedNotAuthenticated"];

                // bind and sanitize incoming data
                $ltiProjectId = !empty($_SESSION['lti_project_id']) ? intval($_SESSION['lti_project_id']) : null;
                if (empty($ltiProjectId)) return ["errorType" => "ltiProjectIdInvalid"];

                // no errors, get lti Project from interfaces_lti_projects table
                $ltiProjectFound = $this->entityManager->getRepository(LtiProject::class)->find($ltiProjectId);

                // no project found with the provided id, return an error
                if (!$ltiProjectFound) {
                    return array('errorType' => 'ltiProjectNotFound');
                }

                // project found, update and save it
                $ltiProjectFound->setIsSubmitted(true);
                $this->entityManager->persist($ltiProjectFound);
                $this->entityManager->flush();

                return array(
                    'success' => true,
                    'id' => $ltiProjectFound->getId()
                );
            },
            'add_or_update_exercise_statement' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "projectAddOrUpdateExerciseStatementNotRetrievedNotAuthenticated"];

                // bind and sanitize data 
                $userId = intval($_SESSION['id']);
                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
                $exerciseStatement = !empty($_POST['exercise_statement'])
                    ? htmlspecialchars(strip_tags(trim($_POST['exercise_statement'])))
                    : '';

                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }

                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);

                // no errors, get the user and project from interfaces_projects
                $user = $this->entityManager->getRepository(User::class)->find($userId);
                $projectExists = $this->entityManager
                    ->getRepository(Project::class)
                    ->findOneBy(array(
                        'id' => $projectId,
                        'user' => $user
                    ));

                if (!$projectExists) {
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }

                if(!$projectExists->getIsExerciseStatementCreator()){
                    array_push($errors, array('errorType' => 'notExerciseStatementCreator'));
                    return array('errors' => $errors);
                }
                
                // update the exercise statement if already exists
                if ($projectExists->getExerciseStatement()) {
                    $projectExists->getExerciseStatement()
                        ->setStatementContent($exerciseStatement);
                    $this->entityManager->flush();
                    return array('success' => true);
                }

                // create exercise statement object
                $exerciseStatementToSave = new ExerciseStatement;
                $exerciseStatementToSave->setStatementContent($exerciseStatement);

                // update project with new exercise statement
                $projectExists->setIsExerciseStatementCreator(true)
                    ->setExerciseStatement($exerciseStatementToSave);
                // $this->entityManager->persist($exerciseStatementToSave);
                $this->entityManager->flush();

                return array('success' => true);
            },
            // RTC UPDATE
            'add_shared_user_to_my_project' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "NotAuthenticated"];

                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
                $sharedUserId = !empty($_POST['shared_user_id']) ? $_POST['shared_user_id'] : null;
                $sharedRight = !empty($_POST['shared_right']) ? $_POST['shared_right'] : null;

                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }
                if (empty($sharedUserId)) {
                    array_push($errors, array('errorType' => 'sharedUserIdInvalid'));
                }

                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);

                // no errors, get the user and project from interfaces_projects
                $user = $this->entityManager->getRepository(User::class)->find($_SESSION['id']);
                $projectExists = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $projectId, 'user' => $user]);

                if (!$projectExists) {
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }

                $actualSharedUser = $projectExists->getSharedUsers();
                if ($actualSharedUser) {
                    $unserializedSharedUsers = @unserialize($actualSharedUser);
                    if (!$unserializedSharedUsers) {
                        $unserializedSharedUsers = [];
                    }
                }

                // Format of the shared users array:
                // [[userId: 1, right: 1], [userId: 2, right: 2]]...
                // check if the user is already shared with the project
                $sharedUserAlreadyShared = false;
                foreach ($unserializedSharedUsers as $sharedUser) {
                    if ($sharedUser['userId'] == $sharedUserId) {
                        $sharedUserAlreadyShared = true;
                        break;
                    }
                }

                // if the user is not already shared, add it to the shared users array
                $newSharedUser = $this->entityManager->getRepository(User::class)->find($sharedUserId);
                if (!$sharedUserAlreadyShared) {
                    if ($newSharedUser) {
                        $fullname = $newSharedUser->getFirstname() . ' ' . $newSharedUser->getLastname();
                    } else {
                        $fullname = $sharedUserId;
                    }
                    array_push($unserializedSharedUsers, ['userId' => $sharedUserId, 'right' => $sharedRight, 'name' => $fullname]);
                }
                // update the shared users array in the project
                $projectExists->setSharedUsers(serialize($unserializedSharedUsers));
                $this->entityManager->flush();

                return array('success' => true);
            },
            'delete_shared_user' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "NotAuthenticated"];

                // bind and sanitize data
                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
                $sharedUsersId = !empty($_POST['shared_user_id']) ? json_decode($_POST['shared_user_id']) : null;

                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }
                if (empty($sharedUsersId)) {
                    array_push($errors, array('errorType' => 'sharedUsersIdInvalid'));
                }

                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);
                // no errors, get the user and project from interfaces_projects


                $user = $this->entityManager->getRepository(User::class)->find($_SESSION['id']);
                $projectExists = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $projectId, 'user' => $user]);
                if (!$projectExists) {
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }

                $sharedUsers = $projectExists->getSharedUsers();

                if (!$sharedUsers) {
                    array_push($errors, array('errorType' => 'sharedUsersNotFound'));
                    return array('errors' => $errors);
                }

                $unserializedSharedUsers = @unserialize($sharedUsers);
                if (!$unserializedSharedUsers) {
                    array_push($errors, array('errorType' => 'sharedUsersNotFound'));
                    return array('errors' => $errors);
                }

                foreach ($unserializedSharedUsers as $user) {
                    if (in_array($user['userId'], $sharedUsersId)) {
                        unset($unserializedSharedUsers[$user['userId']]);
                    }
                }

                $projectExists->setSharedUsers(serialize($unserializedSharedUsers));
                $this->entityManager->flush();

                return ['success' => true];
            },
            'update_shared_users_right' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "NotAuthenticated"];
                // bind and sanitize data
                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
                $sharedUsersId = !empty($_POST['shared_users_id']) ? htmlspecialchars(strip_tags(trim($_POST['shared_users_id']))) : null;
                $sharedUsersRight = !empty($_POST['shared_users_right']) ? intval($_POST['shared_users_right']) : null;

                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }
                /* if (empty($sharedUsers)) {
                    array_push($errors, array('errorType' => 'sharedUsersInvalid'));
                } */

                if ($sharedUsersRight < 1 || $sharedUsersRight > 3) {
                    array_push($errors, array('errorType' => 'sharedUsersRightInvalid'));
                }

                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);
                // no errors, get the user and project from interfaces_projects
                $user = $this->entityManager->getRepository(User::class)->find($_SESSION['id']);
                $projectExists = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $projectId, 'user' => $user]);
                if (!$projectExists) {
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }

                $sharedUsers = $projectExists->getSharedUsers();
                if (!$sharedUsers) {
                    array_push($errors, array('errorType' => 'sharedUsersNotFound'));
                    return array('errors' => $errors);
                }

                $unserializedSharedUsers = @unserialize($sharedUsers);
                if (!$unserializedSharedUsers) {
                    array_push($errors, array('errorType' => 'sharedUsersNotFound'));
                    return array('errors' => $errors);
                }

                foreach ($unserializedSharedUsers as $key => $user) {
                    if ($user['userId'] == $sharedUsersId) {
                        $unserializedSharedUsers[$key]['right'] = $sharedUsersRight;
                    }
                }

                $projectExists->setSharedUsers(serialize($unserializedSharedUsers));
                $this->entityManager->flush();
                return ['success' => true];
            },
            'update_shared_status_for_project' => function () {
                // accept only POST request
                $statusArray = [0, 1, 2];
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "NotAuthenticated"];
                // bind and sanitize data
                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
                $sharedStatus = in_array(intval($_POST['shared_status']), $statusArray) ? intval($_POST['shared_status']) : null;
                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }
                /* if (empty($sharedStatus)) {
                    array_push($errors, array('errorType' => 'sharedStatusInvalid'));
                } */

                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);
                // no errors, get the user and project from interfaces_projects
                $user = $this->entityManager->getRepository(User::class)->find($_SESSION['id']);
                $projectExists = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $projectId, 'user' => $user]);

                if (!$projectExists) {
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }

                if (!in_array($sharedStatus, $statusArray)) {
                    array_push($errors, array('errorType' => 'sharedStatusInvalid'));
                    return array('errors' => $errors);
                }

                $projectExists->setSharedStatus($sharedStatus);
                $this->entityManager->flush();
                return ['success' => true];
            },
            "get_shared_status" => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                // bind and sanitize data
                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }
                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);

                // no errors, get the user and project from interfaces_projects
                $projectExists = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $projectId]);
                if (!$projectExists) {
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }
                return ['success' => true, 'sharedStatus' => $projectExists->getSharedStatus()];
            },
            'update_shared_users_id' => function ($data) {

                $projectJSON = json_decode($_POST['project']);
                $requesterId = !empty($_POST['requesterId']) ? $_POST['requesterId'] : null;
                $project = $this->entityManager->getRepository(Project::class)->findOneBy(array("link" => $projectJSON->link));
                if ($requesterId != null) {
                    $requesterRegular = $this->entityManager->getRepository(Regular::class)->findOneBy(["user" => $requesterId]);
                }
                $projectSharedUsers = $project->getSharedUsers();
                $userChanged = [false, null, null];

                if ($projectSharedUsers) {
                    $unserializedSharedUsers = @unserialize($projectSharedUsers);
                    if (!$unserializedSharedUsers) {
                        $unserializedSharedUsers = [];
                    }
                }
                if ($unserializedSharedUsers) {
                    foreach ($unserializedSharedUsers as $key => $sharedUser) {
                        if ($requesterRegular->getEmail() == $sharedUser['userId']) {
                            $sharedUser['userId'] = $requesterRegular->getUser()->getId();
                            $userChanged = [true, $key, $sharedUser['userId']];
                            break;
                        }
                    }
                }

                if ($userChanged[0]) {
                    $unserializedSharedUsers[$userChanged[1]]['userId'] = $userChanged[2];
                    $project->setSharedUsers(serialize($unserializedSharedUsers));
                }

                if ($userChanged[0]) {
                    $this->entityManager->persist($project);
                    $this->entityManager->flush();
                    return $project;
                } else {
                    return ['status' => false, 'message' => "User déjà à jour"];
                }
            }
        );
    }

    private function getCodeFromAiInterface($project)
    {
        $projectCode = json_decode($project->getCode());
        $arrayKeys = [];
        $trainingDataKeys = $projectCode->trainingDataKeys;
        $fileKeys = $projectCode->fileKeys;
        if (!empty($trainingDataKeys)) {
            array_push($arrayKeys, $trainingDataKeys);
        }
        if (!empty($fileKeys)) {
            array_push($arrayKeys, $fileKeys);
        }
        if (!empty($arrayKeys)) {
            $sessionId = session_id();
            session_write_close();
            $cookie = new SetCookie();
            $cookie->setName('PHPSESSID');
            $cookie->setValue($sessionId);
            $cookie->setDomain($_SERVER["HTTP_HOST"]);
            $cookieJar = new CookieJar(
                false,
                array(
                    $cookie
                )
            );
            $client = new Client();

            // work around to detect https scheme (http for local else https prod and test servers)
            $parts = explode('.', $_SERVER['HTTP_HOST']);
            $scheme = count($parts) > 1 ? 'https' : 'http';

            $response = $client->request('POST', "$scheme://{$_SERVER["HTTP_HOST"]}/routing/Routing.php?controller=cloud&action=duplicate-assets", [
                'form_params' => [
                    'keys' => $arrayKeys
                ],
                'cookies' => $cookieJar
            ]);
            session_start();
            $decodedResponse = json_decode($response->getBody()->getContents());
            if ($decodedResponse->success == true) {
                if (is_array($decodedResponse->assets) && count($decodedResponse->assets) > 0) {
                    $newKey = explode('-', $decodedResponse->assets[0]->to)[0];
                    if (!empty($trainingDataKeys)) {
                        $projectCode->trainingDataKeys = $newKey;
                    }
                    if (!empty($fileKeys)) {
                        $projectCode->fileKeys = $newKey;
                        $projectCode->fileKeysOwner = $_SESSION['id'];
                    }
                    $currentCode = json_encode($projectCode);
                    return $currentCode;
                }
            }
        }

        // $arrayKeys is empty
        return null;
    }

    private function getDuplicatedProject($project, $user, $newProject)
    {
        $newProject->setUser($user);
        $newProject->setDateUpdated();
        $newProject->setCode($project->getCode());
        $newProject->setCodeText($project->getCodeText());
        $newProject->setCodeManuallyModified($project->isCManuallyModified());
        $newProject->setPublic($project->isPublic());
        $newProject->setLink(uniqid());
        $newProject->setInterface($project->getInterface());

        return $newProject;
    }

    public function duplicateAndAssignRelatedExercicesAndTests($project)
    {
        // get python exercice
        $pythonExerciseFound = $project->getExercise();

        $this->entityManager->getConnection()->beginTransaction();
        try {

            // we create and persist the exercise with the related project
            $duplicatedPythonExercise = new ExercisePython($pythonExerciseFound->getFunctionName());

            $this->entityManager->persist($duplicatedPythonExercise);

            // get python test related to this exercise in python_tests table
            $pythonTests = $this->entityManager
                ->getRepository(UnitTests::class)
                ->findByExercise($pythonExerciseFound);

            if (!$pythonTests) throw new \Exception("No python tests found");

            foreach ($pythonTests as $pythonTest) {
                // we create and save the python test with the related exercise
                $duplicatedPythonTest = new UnitTests();
                $duplicatedPythonTest->setExercise($duplicatedPythonExercise);
                $duplicatedPythonTest->setHint($pythonTest->getHint());
                $this->entityManager->persist($duplicatedPythonTest);

                // get unit tests inputs related to this unit test in python_tests_inputs
                $pythonTestInputs = $this->entityManager
                    ->getRepository(UnitTestsInputs::class)
                    ->findByUnitTest($pythonTest);

                // no data from db, go to the catch block
                if (!$pythonTestInputs) throw new \Exception("No python tests inputs found");

                // create new inputs copies for this user and persist them 
                foreach ($pythonTestInputs as $pythonTestInput) {
                    $duplicatedTestInput = new UnitTestsInputs();
                    $duplicatedTestInput->setUnitTest($duplicatedPythonTest);
                    $duplicatedTestInput->setValue($pythonTestInput->getValue());
                    $this->entityManager->persist($duplicatedTestInput);
                }

                // get unit tests outputs related to this unit test in python_tests_outputs
                $pythonTestOutputs = $this->entityManager
                    ->getRepository(UnitTestsOutputs::class)
                    ->findByUnitTest($pythonTest);

                // no data from db, go to the catch block
                if (!$pythonTestOutputs) throw new \Exception("No python tests outputs found");

                // create new outputs copies for this user and persist them
                foreach ($pythonTestOutputs as $pythonTestOutput) {
                    $duplicatedTestOutput = new UnitTestsOutputs();
                    $duplicatedTestOutput->setUnitTest($duplicatedPythonTest);
                    $duplicatedTestOutput->setValue($pythonTestOutput->getValue());
                    $this->entityManager->persist($duplicatedTestOutput);
                }
            }

            // all is ok, save data in db
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
            return $duplicatedPythonExercise;
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollback();
            return false;
        }
    }

    public function duplicateAndAssignRelatedExercicesAndFrames($project)
    {
        // get "not python" exercice (misleading entity name, these exercises use frames like smt32)
        $pythonExerciseFound = $project->getExercise();

        $this->entityManager->getConnection()->beginTransaction();
        try {

            // we create and persist the exercise with the related project
            $duplicatedPythonExercise = new ExercisePython($pythonExerciseFound->getFunctionName());
            //  $duplicatedPythonExercise->setProject($projectDuplicated);
            $this->entityManager->persist($duplicatedPythonExercise);

            // get the frames
            $framesFound = $this->entityManager
                ->getRepository(ExercisePythonFrames::class)
                ->findByExercise($pythonExerciseFound);

            // no data from db, go to the catch block
            if (!$framesFound) throw new \Exception("No frames found");

            // create new frame copies for this user and persist them
            foreach ($framesFound as $frameFound) {
                $duplicatedFrame = new ExercisePythonFrames();
                $duplicatedFrame->setExercise($duplicatedPythonExercise);
                $duplicatedFrame->setFrame($frameFound->getFrame());
                $duplicatedFrame->setComponent($frameFound->getComponent());
                $duplicatedFrame->setValue($frameFound->getValue());
                $this->entityManager->persist($duplicatedFrame);
            }

            // all is ok, save data in db
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
            return $duplicatedPythonExercise;
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollback();
            return false;
        }
    }
   

    /*             'get_shared_link_for_project' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "NotAuthenticated"];
                // bind and sanitize data
                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;

                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }

                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);
                // no errors, get the user and project from interfaces_projects
                $user = $this->entityManager->getRepository(User::class)->find($_SESSION['id']);
                $projectExists = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $projectId,'user' => $user]);

                if (!$projectExists) {
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }

                if (!$projectExists->getSharedLink()) {
                    $sharedLink = md5(uniqid());
                    $projectExists->setSharedLink($sharedLink);
                    $this->entityManager->flush();
                } else {
                    $sharedLink = $projectExists->getSharedLink();
                }

                return ['success' => true, 'shared_link' => $sharedLink];
            }, */
    /*             'add_shared_user_from_link' => function() {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "NotAuthenticated"];
                // bind and sanitize data

                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
                $link = !empty($_POST['shared_link']) ? $_POST['shared_link'] : null;

                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }

                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);

                // no errors, get the user and project from interfaces_projects
                $projectExists = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $projectId]);
                if (!$projectExists) {
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }

                $sharedLink = $projectExists->getSharedLinks();
                if (!$sharedLink) {
                    array_push($errors, array('errorType' => 'sharedLinkInvalid'));
                    return array('errors' => $errors);
                }

                $right = null;
                if ($sharedLink == $link) {
                    $right = $projectExists->getSharedStatus();
                }

                if (!$right) {
                    array_push($errors, array('errorType' => 'sharedLinkInvalid'));
                    return  ['errors' => $errors];
                }

                $sharedUsers = $projectExists->getSharedUsers();
                if ($sharedUsers) {
                    $unserializedSharedUsers = @unserialize($sharedUsers);
                    if (!$unserializedSharedUsers) {
                        $unserializedSharedUsers = [];
                    }
                } else {
                    $unserializedSharedUsers = [];
                }

                foreach ($sharedUsers as $user) {
                    if ($user['userId'] == $_SESSION['id']) {
                        array_push($errors, array('errorType' => 'userAlreadyShared'));
                        return  ['errors' => $errors];
                    }
                }
                array_push($unserializedSharedUsers, ['userId' => $_SESSION['id'], 'right' => $right]);
                $projectExists->setSharedUsers(serialize($unserializedSharedUsers));
                $this->entityManager->flush();

                return ['success' => true];

            } */
}
