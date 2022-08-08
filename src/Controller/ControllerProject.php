<?php

namespace Interfaces\Controller;

use User\Entity\User;
use Interfaces\Entity\UnitTests;
use Interfaces\Entity\Project;
use Interfaces\Entity\LtiProject;
use Interfaces\Entity\ExercisePython;
use Interfaces\Entity\ExercisePythonFrames;
use Interfaces\Entity\UnitTestsInputs;
use Interfaces\Entity\UnitTestsOutputs;

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

                $projectJSON = json_decode($data['project']);
                $requesterId = !empty($_POST['requesterId']) ? intval($_POST['requesterId']) : null;
                $project = $this->entityManager->getRepository(Project::class)->findOneBy(array("link" => $projectJSON->link));
                $projectOwner = $project->getUser();
                $projectSharedUsers = $project->getSharedUsers();
                $projectSharedStatus = $project->getSharedStatus();

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
                        foreach ($unserializedSharedUsers as $sharedUser) {
                            if ($sharedUser['userId'] == $requesterId) {
                                if ($sharedUser['right'] == 'writting') {
                                    $canUpdateProject = true;
                                    break;
                                }
                            }
                        }
                    }
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
            'duplicate' => function ($data) {
                $project = $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array("link" => $data['link']));
                $projectBis = new Project($project->getName(), $project->getDescription());
                $projectBis->setUser($project->getUser());
                $projectBis->setDateUpdated();
                $projectBis->setCode($project->getCode());
                $projectBis->setCodeText($project->getCodeText());
                $projectBis->setCodeManuallyModified($project->isCManuallyModified());
                $projectBis->setPublic($project->isPublic());
                $projectBis->setLink(uniqid());
                $projectBis->setInterface($project->getInterface());
                $this->entityManager->persist($projectBis);
                $this->entityManager->flush();
                return $projectBis;
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
            'lti_teacher_duplicate_project' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "ltiDuplicateTeacherProjectNotRetrievedNotAuthenticated"];

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

                    // we duplicate the project for this user and save it
                    $projectDuplicated = new Project(
                        $project->getName(),
                        $project->getDescription()
                    );

                    $projectDuplicated->setUser($user);
                    $projectDuplicated->setDateUpdated();
                    $projectDuplicated->setCode($project->getCode());
                    $projectDuplicated->setCodeText($project->getCodeText());
                    $projectDuplicated->setCodeManuallyModified($project->isCManuallyModified());
                    $projectDuplicated->setPublic($project->isPublic());
                    $projectDuplicated->setLink(uniqid());
                    $projectDuplicated->setInterface($project->getInterface());

                    // set exercise 
                    if ($project->getExercise()) {
                        $projectDuplicated->setExercise($project->getExercise());
                        //$projectDuplicated->setIsExerciseCreator(false);
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

                $projectExists->setExerciseStatement($exerciseStatement);
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
                $projectExists = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $projectId,'user' => $user]);

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
                // [[userId: 1, right: "reading"], [userId: 2, right: "writting"]]...
                // check if the user is already shared with the project
                $sharedUserAlreadyShared = false;
                foreach ($unserializedSharedUsers as $sharedUser) {
                    if ($sharedUser['userId'] == $sharedUserId) {
                        $sharedUserAlreadyShared = true;
                        break;
                    }
                }

                // if the user is not already shared, add it to the shared users array
                if (!$sharedUserAlreadyShared) {
                    array_push($unserializedSharedUsers, ['userId' => $sharedUserId, 'right' => $sharedRight]);
                }

                // update the shared users array in the project
                $projectExists->setSharedUsers(serialize($unserializedSharedUsers));
                $this->entityManager->flush();

                return array('success' => true);
            },
            'get_shared_link_for_project' => function () {
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

                if (!$projectExists->getSharedLinks()) {
                    $sharedLinks = ["writting" => md5(uniqid()), "reading" => md5(uniqid())];
                    $projectExists->setSharedLinks(serialize($sharedLinks));
                    $this->entityManager->flush();
                } else {
                    $sharedLinks = @unserialize($projectExists->getSharedLinks());
                }

                return ['success' => true, 'shared_links' => $sharedLinks];
            },
            'add_shared_user_from_link' => function() {
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

                $sharedLinks = @unserialize($projectExists->getSharedLinks());
                if (!$sharedLinks) {
                    array_push($errors, array('errorType' => 'sharedLinkInvalid'));
                    return array('errors' => $errors);
                }

                $right = null;
                foreach ($sharedLinks as $sharedLink) {
                    if ($sharedLink["writting"] == $link) {
                        $right = "writting";
                    } else if ($sharedLink["reading"] == $link) {
                        $right = "reading";
                    } else {
                        array_push($errors, array('errorType' => 'sharedLinkInvalid'));
                        return  ['errors' => $errors];
                    }
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

            },
            'delete_shared_user' => function() {
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
                $projectExists = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $projectId,'user' => $user]);
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
            'update_shared_users_right' => function() {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "NotAuthenticated"];
                // bind and sanitize data
                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
                $sharedUsers = !empty($_POST['shared_users_id']) ? json_decode($_POST['shared_users_id']) : null;

                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }
                if (empty($sharedUsers)) {
                    array_push($errors, array('errorType' => 'sharedUsersInvalid'));
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
                    foreach ($sharedUsers as $sharedUser) {
                        if ($user['userId'] == $sharedUser[0]) {
                            $unserializedSharedUsers[$user['userId']]['right'] = $sharedUser[1];
                        }
                    }
                }

                $projectExists->setSharedUsers(serialize($unserializedSharedUsers));
                $this->entityManager->flush();
                return ['success' => true];
            },
            'update_shared_status_for_project' => function() {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "NotAuthenticated"];
                // bind and sanitize data
                $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
                $sharedStatus = !empty($_POST['shared_status']) ? boolval($_POST['shared_status']) : null;
                
                // check for errors
                $errors = [];
                if (empty($projectId)) {
                    array_push($errors, array('errorType' => 'projectIdInvalid'));
                }
                if (empty($sharedStatus)) {
                    array_push($errors, array('errorType' => 'sharedStatusInvalid'));
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

                $projectExists->setSharedLinkRights($sharedStatus);
                $this->entityManager->flush();
                return ['success' => true];
            }
        );
    }

    /* public function assignRelatedExercicesAndTestsToStudent($project,$projectDuplicated){
        // get python exercice
         $pythonExerciseFound = $project->getExercise();
 
         if(!$pythonExerciseFound){
             // no exercise for this project, return true to go back in main method
             return true;
         }
 


         $this->entityManager->getConnection()->beginTransaction();
         try{
             
             // we create and persist the exercise with the related project
             $duplicatedPythonExercise = new ExercisePython($pythonExerciseFound->getFunctionName());
             $duplicatedPythonExercise->setProject($projectDuplicated);
             $this->entityManager->persist($duplicatedPythonExercise);          
 
             // get python test related to this exercise in python_tests table
             $pythonTests = $this->entityManager
                 ->getRepository(UnitTests::class)
                 ->findByExercise($pythonExerciseFound);
 
             if(!$pythonTests) throw new \Exception("No python tests found");
            
             foreach($pythonTests as $pythonTest){
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
                 if(!$pythonTestInputs) throw new \Exception("No python tests inputs found");
                 
                 // create new inputs copies for this user and persist them 
                 foreach($pythonTestInputs as $pythonTestInput){
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
                 if(!$pythonTestOutputs) throw new \Exception("No python tests outputs found");
                 
                 // create new outputs copies for this user and persist them
                 foreach($pythonTestOutputs as $pythonTestOutput){
                     $duplicatedTestOutput = new UnitTestsOutputs();
                     $duplicatedTestOutput->setUnitTest($duplicatedPythonTest);
                     $duplicatedTestOutput->setValue($pythonTestOutput->getValue());
                     $this->entityManager->persist($duplicatedTestOutput);
                     
                 }
             }  
             
             // all is ok, save data in db
             $this->entityManager->flush();
             $this->entityManager->getConnection()->commit();
             return true;
            
         } catch(\Exception $e){
             $this->entityManager->getConnection()->rollback();
             return false; 
         }
         
     }
     
     public function assignRelatedExercicesAndFramesToStudent($project,$projectDuplicated){
         // get "not python" exercice (misleading entity name, these exercises use frames like smt32)
         $pythonExerciseFound = $project->getExercise();
         
         if(!$pythonExerciseFound){
             // no exercise for this project, return true to go back in main method
             return true;
         }
 
         $this->entityManager->getConnection()->beginTransaction(); 
         try{
             
             // we create and persist the exercise with the related project
             $duplicatedPythonExercise = new ExercisePython($pythonExerciseFound->getFunctionName());
             $duplicatedPythonExercise->setProject($projectDuplicated);
             $this->entityManager->persist($duplicatedPythonExercise);
 
             // get the frames
             $framesFound = $this->entityManager
                 ->getRepository(ExercisePythonFrames::class)
                 ->findByExercise($pythonExerciseFound);
             
             // no data from db, go to the catch block
             if(!$framesFound) throw new \Exception("No frames found");
 
             // create new frame copies for this user and persist them
             foreach($framesFound as $frameFound){
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
             return true;
             
         } catch(\Exception $e){
             $this->entityManager->getConnection()->rollback();
             return false;
         }
     } */
}
