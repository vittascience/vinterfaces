<?php

namespace Interfaces\Controller;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use User\Entity\User;
use User\Entity\Regular;
use Interfaces\Entity\Project;
use Interfaces\Entity\UnitTests;
use Interfaces\Entity\LtiProject;
use Interfaces\Entity\ExercisePython;
use Interfaces\Entity\UnitTestsInputs;
use Interfaces\Entity\UnitTestsOutputs;
use Interfaces\Entity\ExerciseStatement;
use Interfaces\Entity\ExercisePythonFrames;
use Utils\Mailer;
use Utils\Traits\UtilsAssetsTrait;

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
                    ->getSummaryPublicProjects(array("public" => true, "deleted" => false, "interface" => $data['interface']));
            },
            'get_by_link' => function ($data) {
                $link = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data['link']);
                return $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array("link" => $link, "deleted" => false));
            },
            'get_by_user' => function ($data) {
                return $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->getSummaryPersonalProjects(array("user" => $this->user['id'], "deleted" => false, "interface" => $data['interface']));
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
                if (array_key_exists('options', $data)) {
                    $project->setOptions($data['options']);
                }
                $this->entityManager->persist($project);
                $this->entityManager->flush();
                return $project->getLink();
            },
            'add' => function ($data) {
                try {
                    $nameSanitized = $data['name'] ? htmlspecialchars($data['name']) : null;
                    $descriptionSanitized = $data['description'] ? htmlspecialchars($data['description']) : null;

                    if (!$nameSanitized) {
                        return ['error' => 'missing_data'];
                    }
                    
                    if ($this->user === null || !isset($this->user['id'])) {
                        http_response_code(401);
                        return ['error' => 'user_not_connected'];
                    }
                    
                    $user = $this->entityManager->getRepository('User\Entity\User')->findOneBy(array("id" => $this->user['id']));
                    $project = new Project($nameSanitized, $descriptionSanitized);
                    $project->setUser($user);
                    $project->setDateUpdated();
                    $project->setCode($data['code']);
                    $project->setCodeText($data['codeText']);
                    $project->setMode($data['mode'] ?? false);
                    $project->setCodeManuallyModified($data['codeManuallyModified']);
                    $project->setPublic($data['public']);
                    $project->setLink(uniqid());
                    $project->setInterface($data['interface']);
                    $project->setSharedStatus($data['sharedStatus'] ?? 0);
                    if (isset($data['activitySolve'])) {
                        $project->setActivitySolve(true);
                    }
                    if (array_key_exists('options', $data)) {
                        $project->setOptions($data['options']);
                    }
                    $this->entityManager->persist($project);
                    $this->entityManager->flush();
                    return $project;
                } catch (\Exception $e) {
                    return ['error' => $e->getMessage()];
                }
            },
            'update_my_project' => function ($data) {

                /**
                 * RTC update
                 */

                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                $jwtToken = !empty($_POST['jwtToken']) ? $_POST['jwtToken'] : null;
                $userId = null;
                $incomingProject = null;
                $errors = [];
                if (isset($_POST['jwtToken'])) {
                    // bind and sanitize incoming jwt token
                    $jwtToken = !empty($_POST['jwtToken']) ? htmlspecialchars(strip_tags(trim($_POST['jwtToken']))) : null;
                    // the jwt is empty
                    if (empty($jwtToken)) {
                        $errors[] = ["errorType" => "no jwt token received"];
                        return ["errors" => $errors];
                    }

                    try {
                        $validatedToken = JWT::decode(
                            $jwtToken,
                            JWK::parseKeySet(json_decode(file_get_contents("https://vittascience-rtc.com/jwks"), true))
                        );
                    } catch (\Exception $e) {
                        $errors[] = ["errorType" => "token not validated"];
                        return ["errors" => $errors];
                    }

                    if (!empty($validatedToken->project)) $incomingProject = json_decode($validatedToken->project);
                    $userId = $validatedToken->sub;
                } else {
                    $incomingProject = json_decode($_POST['project']);
                    $userId = $_SESSION['id'];
                }

                if (empty($userId)) {
                    $errors[] = ["errorType" => "noUserId"];
                    return ["errors" => $errors];
                }

                $sanitizedProject = $this->sanitizeIncomingProject($incomingProject);
                $errors = $this->checkForProjectErrors($sanitizedProject);
                if (!empty($errors)) return ["errors" => $errors];

                $project = $this->entityManager->getRepository(Project::class)->findOneBy(array("link" => $sanitizedProject->link));
                $requesterRegular = $this->entityManager->getRepository(Regular::class)->findOneBy(["user" => $userId]);
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
                if ($projectOwner->getId() == $userId) {
                    $canUpdateProject = true;
                } else {
                    if ($unserializedSharedUsers) {
                        foreach ($unserializedSharedUsers as $key => $sharedUser) {
                            if ($sharedUser['userId'] == $userId) {
                                if ($sharedUser['right'] == 2) {
                                    $canUpdateProject = true;
                                    break;
                                }
                            }

                            /* @toBeRemoved @Sebastien 26/10/2022
                            This checked is already done at the entrance of the server so it is not necessary here. To be removed.
                            else if ($requesterRegular->getEmail() == $sharedUser['userId']) {
                                if ($sharedUser['right'] == 2) {
                                    $sharedUser['userId'] = $requesterRegular->getUser()->getId();
                                    $canUpdateProject = true;
                                    $userChanged = [true, $key, $sharedUser['userId']];
                                    break;
                                }
                            } */
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
                    $project->setCode($sanitizedProject->code);
                    $project->setName($sanitizedProject->name);
                    $project->setDescription($sanitizedProject->description);
                    $project->setCodeText($sanitizedProject->codeText);
                    $project->setMode($sanitizedProject->mode);
                    $project->setCodeManuallyModified($sanitizedProject->codeManuallyModified);
                    $project->setPublic($sanitizedProject->public);
                    if (property_exists($sanitizedProject, 'options')) {
                        $project->setOptions(json_encode($sanitizedProject->options));
                    }
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
                $projectName = !empty($name) ? $name : $project->getName();
                $projectDescription = !empty($description) ? $description : $project->getDescription();
                $projectIsPublic = $isPublic;


                // create the project and generic properties
                $newProject = new Project($projectName, $projectDescription);
                $projectDuplicated = $this->getDuplicatedProject($project, $user, $newProject);

                // set additional project's properties
                $projectDuplicated->setName($projectName);
                $projectDuplicated->setPublic($projectIsPublic);
                $projectDuplicated->setCode($currentCode);

                // save it to generate its Id with doctrine
                $this->entityManager->persist($projectDuplicated);
                $this->entityManager->flush();

                // we have an exercise, duplicate it creating a new exercise linked to the dupliaced project
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

                // we have an exercise statement, duplicate it creating a new exercise statement linked to the dupliaced project
                if ($project->getExerciseStatement()) {
                    // create exercise statement object
                    $exerciseStatementToSave = new ExerciseStatement;
                    $exerciseStatementToSave->setStatementContent($project->getExerciseStatement()->getStatementContent());
                    $this->entityManager->flush();

                    $projectDuplicated->setExerciseStatement($exerciseStatementToSave);
                    $projectDuplicated->setIsExerciseStatementCreator(true);
                }

                // save the project again with additional exercise and exercise statement eventually
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
            'get_all_user_projects_for_all_interfaces' => function ($data) {
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
                            ->findBy(array("deleted" => false, "user" => $userFetched, "public" => true));
                    } else {
                        return $this->entityManager->getRepository('Interfaces\Entity\Project')
                            ->findBy(array("deleted" => false, "user" => $userFetched));
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

                // initialize empty $errors array
                $errors = [];

                // accept only connected user
                if (empty($_SESSION['id'])) {
                    array_push($errors, array('errorType' => 'ltiDuplicateProjectForStudentNotRetrievedNotAuthenticated'));
                    return array('errors' => $errors);
                }

                // bind and sanitize incoming data
                $userId = intval($_SESSION['id']);
                $ltiCourseId = !empty($_POST['course_id']) ? $_POST['course_id'] : null;
                $ltiResourceLinkId = !empty($_POST['resource_link_id']) ? $_POST['resource_link_id'] : null;
                $projectLink = !empty($_POST['link']) ? htmlspecialchars(strip_tags(trim($_POST['link']))) : '';
                $targetLinkUri = !empty($_POST['target_link_uri']) ? $_POST['target_link_uri'] : null;

                //if(empty($ltiCourseId)) $errors['ltiCourseIdInvalid'] = true;
                if (empty($ltiResourceLinkId)) array_push($errors, array('errorType' => 'resourceLinkIdInvalid'));
                if (empty($targetLinkUri)) array_push($errors, array('errorType' => 'targetLinkUriInvalid'));
                if (empty($projectLink)) array_push($errors, array('errorType' => 'projectLinkInvalid'));

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
                    array_push($errors, array('errorType' => 'resourceLinkIdInvalid'));
                    return array('errors' => $errors);
                }

                //set up defaults params( without $ltiCourseId as it is optional)
                $queryParams = array(
                    'user' => $user->getId(),
                    'ltiResourceLinkId' => $ltiResourceLinkId,
                    'ltiTargetLinkUri' => $targetLinkUri,
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
                    // we duplicate the project
                    $projectDuplicated = $this->getDuplicatedProject($project, $user, $newProject);

                    // set additional properties 
                    if ($project->getExercise()) {
                        $projectDuplicated->setExercise($project->getExercise());
                    }
                    if ($project->getExerciseStatement()) {
                        $projectDuplicated->setExerciseStatement($project->getExerciseStatement());
                    }

                    $this->entityManager->persist($projectDuplicated);
                    $this->entityManager->flush();

                    // we create a ltiProject entry in interfaces_lti_projects and save it
                    $ltiProject = new LtiProject();
                    $ltiProject->setUser($user);
                    $ltiProject->setUserProjectLink($projectDuplicated->getLink());
                    $ltiProject->setLtiResourceLinkId($ltiResourceLinkId);
                    $ltiProject->setLtiTargetLinkUri($targetLinkUri);
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

                $errors = [];
                // accept only connected user
                if (empty($_SESSION['id'])) {
                    array_push($errors, array('errorType' => 'ltiDuplicateTeacherProjectNotRetrievedNotAuthenticated'));
                    return array('errors' => $errors);
                }

                // bind and sanitize incoming data
                $ltiProjectId = !empty($_SESSION['lti_project_id']) ? intval($_SESSION['lti_project_id']) : null;
                if (empty($ltiProjectId)) {
                    array_push($errors, array('errorType' => 'ltiProjectIdInvalid'));
                    return array('errors' => $errors);
                }

                // no errors, get lti Project from interfaces_lti_projects table
                $ltiProjectFound = $this->entityManager->getRepository(LtiProject::class)->find($ltiProjectId);

                // no project found with the provided id, return an error
                if (!$ltiProjectFound) {
                    array_push($errors, array('errorType' => 'ltiProjectNotFound'));
                    return array('errors' => $errors);
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
            'lti_teacher_submit_project' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                $errors = [];
                // accept only connected user
                if (empty($_SESSION['id'])) {
                    array_push($errors, array('errorType' => 'ltiTeacherSubmitProjectNotRetrievedNotAuthenticated'));
                    return array('errors' => $errors);
                }

                // bind and sanitize incoming data
                $projectLink = !empty($_POST['project_link']) ? htmlspecialchars(strip_tags(trim($_POST['project_link']))) : '';
                if (empty($projectLink)) {
                    array_push($errors, array('errorType' => 'projectLinkInvalid'));
                    return array('errors' => $errors);
                }

                // no errors, get lti Project from interfaces_lti_projects table
                $ltiProjectFound = $this->entityManager->getRepository(LtiProject::class)->findOneBy(array(
                    'userProjectLink' => $projectLink
                ));

                // no project found with the provided id, create it
                if (!$ltiProjectFound) {
                    $ltiCourseId = !empty($_POST['course_id']) ? $_POST['course_id'] : null;
                    $userId = intval($_SESSION['id']);

                    $projectFound = $this->entityManager->getRepository(Project::class)->findOneBy(array(
                        'link' => $projectLink
                    ));
                    if (!$projectFound)  return array('msg' => 'projectNotFound');

                    $user = $this->entityManager->getRepository(User::class)->find($userId);
                    if (!$user)  return array('msg' => 'userNotFound');

                    $ltiProject = new LtiProject();
                    $ltiProject->setUser($user);
                    $ltiProject->setUserProjectLink($projectLink);
                    $projectInterface = $projectFound->getInterface();
                    if ($projectFound->getInterface() == 'ai') {
                        $projectInterface = "ai-{$projectFound->getMode()}";
                    }
                    $ltiProject->setLtiResourceLinkId($projectInterface);
                    
                    if ($ltiCourseId) $ltiProject->setLtiCourseId($ltiCourseId);

                    $ltiProject->setIsSubmitted(true);
                    $this->entityManager->persist($ltiProject);
                    $this->entityManager->flush();
                    return array(
                        'success' => true,
                        'id' => $ltiProject->getId()
                    );
                }

                // project found, update and save it
                $ltiProjectFound->setIsSubmitted(true);
                $this->entityManager->flush();

                return array(
                    'success' => true,
                    'id' => $ltiProjectFound->getId()
                );
            },
            'lti_save_draft_project' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                $errors = [];
                // accept only connected user
                if (empty($_SESSION['id'])) {
                    array_push($errors, array('errorType' => 'ltiSaveDraftProjectNotRetrievedNotAuthenticated'));
                    return array('errors' => $errors);
                }

                // bind and sanitize incoming data
                $userId = intval($_SESSION['id']);
                $projectLink = !empty($_POST['project_link']) ? htmlspecialchars(strip_tags(trim($_POST['project_link']))) : '';
                $ltiCourseId = !empty($_POST['lti_course_id']) ? htmlspecialchars(strip_tags(trim($_POST['lti_course_id']))) : '';

                // check for errors and return them if any
                if (empty($projectLink)) array_push($errors, array('errorType' => 'projectLinkInvalid'));
                if (!empty($errors))  return array('errors' => $errors);
                $user = $this->entityManager->getRepository(User::class)->find($userId);

                // retrieve the main project in interfaces_projects table
                $mainProject = $this->entityManager->getRepository(Project::class)->findOneBy(array(
                    'user' => $user,
                    'link' => $projectLink
                ));

                // main project not found, return an error
                if (!$mainProject) {
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }

                // set default params for doctrine query
                $queryParams = array(
                    'user' => $user,
                    'userProjectLink' => $projectLink,
                    'isSubmitted' => false
                );
                // add additional param if needed
                if (!empty($ltiCourseId)) $queryParams['ltiCourseId'] = $ltiCourseId;


                $ltiProjectFound = $this->entityManager
                    ->getRepository(LtiProject::class)
                    ->findOneBy($queryParams);

                // no project create it
                if (!$ltiProjectFound) {
                    $ltiProject = new LtiProject();
                    $ltiProject->setUser($user);
                    $ltiProject->setUserProjectLink($projectLink);
                    $projectInterface = $mainProject->getInterface();
                    if ($mainProject->getInterface() == 'ai') {
                        $projectInterface = "ai-{$mainProject->getMode()}";
                    }
                    $ltiProject->setLtiResourceLinkId($projectInterface);
                    $ltiProject->setIsSubmitted(false);
                    if ($ltiCourseId) {
                        $ltiProject->setLtiCourseId($ltiCourseId);
                    }

                    $this->entityManager->persist($ltiProject);
                    $this->entityManager->flush();
                    return $ltiProject->jsonSerialize();
                }

                return $ltiProjectFound->jsonSerialize();
            },
            'lti_teacher_reset_project' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "ltiTeacherResetProjectNotRetrievedNotAuthenticated"];

                // initialize empty errors array
                $errors = [];
                $projectLink = !empty($_POST['project_link']) ? htmlspecialchars(strip_tags(trim($_POST['project_link']))) : '';
                $code = !empty($_POST['code']) ? trim($_POST['code']) : '';
                $codeText = !empty($_POST['code_text']) ? trim($_POST['code_text']) : '';

                if(empty($projectLink)){
                    array_push($errors, array('errorType' => 'projectLinkInvalid'));
                    return array('errors' => $errors);
                }

                // retrieve the project from db
                $projectFound = $this->entityManager->getRepository(Project::class)->findOneByLink($projectLink);
                if(!$projectFound){
                    array_push($errors, array('errorType' => 'projectNotFound'));
                    return array('errors' => $errors);
                }

                // update code and code text if necessary
                if(!empty($code)) $projectFound->setCode($code);
                if(!empty($codeText)) $projectFound->setCodeText($codeText);

                
                // current project is exercise statement creator
                if($projectFound->getIsExerciseStatementCreator() && $projectFound->getExerciseStatement()) {
                    
                    $exerciseStatement = $this->entityManager->getRepository(ExerciseStatement::class)->find($projectFound->getExerciseStatement()->getId());

                    // update the project, then remove the statement from interfaces_exercise_statement
                    $projectFound->setIsExerciseStatementCreator(false);
                    $projectFound->setExerciseStatement(null);
                    $this->entityManager->remove($exerciseStatement);
                    $this->entityManager->flush();
                }

                if($projectFound->getIsExerciseCreator()){
                    $exercise = $this->entityManager->getRepository(ExercisePython::class)->find($projectFound->getExercise()->getId());

                        // update the project, then remove the exercise from python_exercise
                        $projectFound->setIsExerciseCreator(false);
                        $projectFound->setExercise(null);
                        $this->entityManager->remove($exercise);
                        $this->entityManager->flush();
                }
                $this->entityManager->flush();
                return $projectFound;
                
            },
            'lti_student_reinitialize_project' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "ltiStudentReinitializeProjectNotRetrievedNotAuthenticated"];

                // initialize empty errors array
                $errors = [];
                $teacherProjectLink = !empty($_POST['teacher_project_link']) ? htmlspecialchars(strip_tags(trim($_POST['teacher_project_link']))) : '';
                $studentProjectLink = !empty($_POST['student_project_link']) ? htmlspecialchars(strip_tags(trim($_POST['student_project_link']))) : '';

                if(empty($teacherProjectLink)) array_push($errors, array('errorType' => 'teacherProjectLinkInvalid'));
                if(empty($studentProjectLink)) array_push($errors, array('errorType' => 'studentProjectLinkInvalid'));
                if(!empty($errors)) return array('errors' => $errors);

                // retrieve the project from db
                $teacherProject = $this->entityManager->getRepository(Project::class)->findOneByLink($teacherProjectLink);
                if(!$teacherProject)array_push($errors, array('errorType' => 'teacherProjectNotFound'));
                
                $studentProject = $this->entityManager->getRepository(Project::class)->findOneByLink($studentProjectLink);
                if(!$studentProject)array_push($errors, array('errorType' => 'studentProjectNotFound'));

                // some errors, return them
                if(!empty($errors)) return array('errors' => $errors);

                // update code and code text 
                $studentProject->setCode($teacherProject->getCode());
                $studentProject->setCodeText($teacherProject->getCodeText());

                $this->entityManager->flush();
                return $studentProject;
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
                    ? htmlspecialchars(trim($_POST['exercise_statement']))
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

                // the user is not the creator and we have an exercise already stored
                if (!$projectExists->getIsExerciseStatementCreator() && $projectExists->getExerciseStatement()) {
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

                $unserializedSharedUsers = [];
                $actualSharedUser = $projectExists->getSharedUsers();
                /* if (strlen($actualSharedUser) > 2 ) {
                    $unserializedSharedUsers = @unserialize($actualSharedUser);
                } */
                if (str_starts_with($actualSharedUser, "a:")) {
                    $unserializedSharedUsers = @unserialize($actualSharedUser);
                }
                /* if ($actualSharedUser) {
                    $unserializedSharedUsers = @unserialize($actualSharedUser);
                    if (!$unserializedSharedUsers) {
                        $unserializedSharedUsers = [];
                    }
                } */

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
                        $fullname = $newSharedUser->getFirstname() . ' ' . $newSharedUser->getSurname();
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
                $sharedUserId = !empty($_POST['shared_user_id']) ? htmlspecialchars(strip_tags(trim($_POST['shared_user_id']))) : null;

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
                $isRemoved = false;
                for ($i = 0; $i < count($unserializedSharedUsers); $i++) {
                    if ($unserializedSharedUsers[$i]['userId'] == $sharedUserId) {
                        unset($unserializedSharedUsers[$i]);
                        $isRemoved = true;
                        break;
                    }
                }
                if (!$isRemoved) {
                    array_push($errors, array('errorType' => 'userNotRemoved'));
                    return array('errors' => $errors);
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
                $sharedUsersRight = !empty($_POST['shared_users_right']) ? htmlspecialchars(strip_tags(trim($_POST['shared_users_right']))) : null;

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
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                $jwtToken = !empty($_POST['jwtToken']) ? $_POST['jwtToken'] : null;
                $userId = null;
                $incomingProject = null;
                $errors = [];

                // bind and sanitize incoming jwt token
                $jwtToken = !empty($_POST['jwtToken']) ? htmlspecialchars(strip_tags(trim($_POST['jwtToken']))) : null;
                // the jwt is empty
                if (empty($jwtToken)) {
                    $errors[] = ["errorType" => "no jwt token received"];
                    return ["errors" => $errors];
                }

                try {
                    $validatedToken = JWT::decode(
                        $jwtToken,
                        JWK::parseKeySet(json_decode(file_get_contents("https://vittascience-rtc.com/jwks"), true))
                    );
                } catch (\Exception $e) {
                    $errors[] = ["errorType" => "token not validated"];
                    return ["errors" => $errors];
                }

                if (!empty($validatedToken->project)) $incomingProject = json_decode($validatedToken->project);
                $userId = $validatedToken->sub;


                if (empty($userId)) {
                    $errors[] = ["errorType" => "noUserId"];
                    return ["errors" => $errors];
                }

                $sanitizedProject = $this->sanitizeIncomingProject($incomingProject);
                $errors = $this->checkForProjectErrors($sanitizedProject);
                if (!empty($errors)) return ["errors" => $errors];

                $project = $this->entityManager->getRepository(Project::class)->findOneBy(array("link" => $sanitizedProject->link));
                $requesterRegular = $this->entityManager->getRepository(Regular::class)->findOneBy(["user" => $userId]);
                if (empty($requesterRegular)) {
                    $errors[] = ["errorType" => "noUserRegular"];
                    return ["errors" => $errors];
                }
                $projectSharedUsers = $project->getSharedUsers();
                $userChanged = [false, null, null];
                $unserializedSharedUsers = [];
                if ($projectSharedUsers) {
                    $unserializedSharedUsers = @unserialize($projectSharedUsers);
                    if (!$unserializedSharedUsers) {
                        $unserializedSharedUsers = [];
                    }
                }
                if ($unserializedSharedUsers) {
                    for ($i = 0; $i < count($unserializedSharedUsers); $i++) {
                        $sharedUser = $unserializedSharedUsers[$i];
                        if ($requesterRegular->getEmail() == $sharedUser['userId']) {
                            $sharedUser['userId'] = $requesterRegular->getUser()->getId();
                            $userChanged = [true, $i, $sharedUser['userId']];
                            break;
                        }
                    }
                }

                if ($userChanged[0] == false) {
                    // to be improved in the future for better front end handling
                    return ['status' => false, 'message' => "User déjà à jour"];
                }
                $unserializedSharedUsers[$userChanged[1]]['userId'] = $userChanged[2];
                $project->setSharedUsers(serialize($unserializedSharedUsers));
                $this->entityManager->persist($project);
                $this->entityManager->flush();
                return $project;
            },
            'get_signed_link'=> function(){
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                header("Access-Control-Allow-Origin: *");
                $link = !empty($_POST['link']) ? $_POST['link'] : '';
                //$project = $this->entityManager->getRepository(Project::class)->findOneBy(array("link" => $link));
                $iss = "https://{$_SERVER['HTTP_HOST']}";
                $privateKey = file_get_contents(__DIR__ . "/../../../../../temporaryKeys/rtcPrivateKey.pem");
                $userId = isset($_SESSION['id']) ? $_SESSION['id'] : 'anonymous';
                $userIp = $this->returnIpAddress();
                $kid = "rtc";

                $jwtClaims = [
                    "iss" => $iss,
                    "sub" => $userId,
                    "aud" => "rtc",
                    "link" => $link,
                    "exp" => time() + 7200,
                    "iat" => time(),
                    "ip" => $userIp
                ];

                $token = JWT::encode(
                    $jwtClaims,
                    $privateKey,
                    'RS256',
                    $kid
                );
                
                return $token;
            },
            'rtc_project_access_ask'=> function(){
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                $link = !empty($_POST['link']) ? htmlspecialchars(strip_tags(trim($_POST['link']))) : '';
                if ($link == '') return ["success" => false, "error" => "Empty link", "message" => "Lien de projet non valide", "bg" => "bg-danger"];
                $email = !empty($_POST['email']) ? htmlspecialchars(strip_tags(trim($_POST['email']))) : null;
                if ($email == null) return ["success" => false, "error" => "Empty email", "message" => "Adresse e-mail incorrecte", "bg" => "bg-danger"];
                $message = !empty($_POST['message']) ? htmlspecialchars(strip_tags(trim($_POST['message']))) : '';
                $project = $this->entityManager->getRepository(Project::class)->findOneBy(array("link" => $link));
                $interface = $project->getInterface();
                $projectName = $project->getName();
                $projectOwner = $this->entityManager->getRepository(Regular::class)->findOneBy(["user" => $project->getUser()]);
                $projectOwnerEmail = $projectOwner->getEmail();
                $body = `<br>
                <p>
                    Bonjour, un utilisateur a souhaité avoir accès à votre projet Vittascience suivant : {$projectName}.
                </p>
                <p>
                    Voici son adresse e-mail : {$email} <br> ainsi que son message : {$message}
                </p>
                <p>
                    Vous pouvez lui donner accès à votre projet en cliquant sur le lien suivant : <br><a href='https://vittascience.com/{$interface}/?link={$link}'>https://vittascience.com/{$interface}/?link={$link}</a><br>
                    et en cliquant sur le bouton "Partager" en haut à gauche de la page, puis dans l'onglet "envoyer par e-mail" entrez son adresse e-mail ainsi que le droit que vous souhaitez lui attribuer.
                </p>
                <p>
                    Si vous ne souhaitez pas donner accès à votre projet à cet utilisateur, vous pouvez ignorer cet e-mail.
                </p>
                <p>
                    Cordialement, <br>
                    L'équipe Vittascience
                </p>
                <br>`;
                $sendSuccess = Mailer::sendMail($projectOwnerEmail,  "Demande d'accès à votre projet Vittascience" , $body, strip_tags($body), "fr_defaultMailerTemplate");
                if(!$sendSuccess){
                    return ["success" => false, "message" => "Une erreur est survenue lors de l'envoi du mail", "bg" => "bg-danger"];
                } 
                return ["success" => true, "message" => "Votre demande a bien été envoyée.", "bg" => "bg-success"];
            },
            'get_signed_project_rtc'=> function(){
                //return 'ok';
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                $jwtToken = !empty($_POST['jwtToken']) ? htmlspecialchars(strip_tags(trim($_POST['jwtToken']))) : null;
                try {
                    $validatedToken = JWT::decode(
                        $jwtToken, 
                        JWK::parseKeySet(json_decode(file_get_contents("https://vittascience-rtc.com/jwks"), true))
                    );
                } catch (\Exception $e) {
                    $errors[] = ["errorType" => "token not validated"];
                    return ["errors" => $errors];
                }

                if(!empty($validatedToken->link)){
                    $link = $validatedToken->link;
                }
                //$link = !empty($_POST['link']) ? htmlspecialchars(strip_tags(trim($_POST['link']))) : null;
                $project = $this->entityManager->getRepository(Project::class)->findOneBy(array("link" => $link));
                $iss = "https://{$_SERVER['HTTP_HOST']}";
                $privateKey = file_get_contents(__DIR__ . "/../../../../../temporaryKeys/rtcPrivateKey.pem");

                $kid = "rtc";

                $jwtClaims = [
                    "iss" => $iss,
                    "sub" => 'anonymous',
                    "aud" => "rtc",
                    "project" => $project,
                    "exp" => time() + 7200,
                    "iat" => time()
                ];

                $token = JWT::encode(
                    $jwtClaims,
                    $privateKey,
                    'RS256',
                    $kid
                );

                return $token;
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
            $response = UtilsAssetsTrait::duplicateAssets($this->entityManager, $arrayKeys);
            if ($response['success'] == true) {
                if (is_array($response['assets']) && count($response['assets']) > 0) {
                    $newKey = explode('-', $response['assets'][0]['to'])[0];
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
        $newProject->setMode($project->getMode());
        $newProject->setOptions($project->getOptions());

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

    private function sanitizeIncomingProject($incomingProject)
    {
        $project = new \stdClass();
        $project->code = !empty($incomingProject->code) ? $incomingProject->code : null;
        $project->name = !empty($incomingProject->name) ? htmlspecialchars($incomingProject->name) : null;
        $project->description = !empty($incomingProject->description) ? htmlspecialchars($incomingProject->description) : null;
        $project->codeText = !empty($incomingProject->codeText) ? $incomingProject->codeText : null;
        $project->mode = !empty($incomingProject->mode) ? htmlspecialchars(strip_tags(trim($incomingProject->mode))) : null;
        $project->codeManuallyModified = !empty($incomingProject->codeManuallyModified) ? filter_var($incomingProject->codeManuallyModified, FILTER_VALIDATE_BOOLEAN) : false;
        $project->public = !empty($incomingProject->public) ? filter_var($incomingProject->public, FILTER_VALIDATE_BOOLEAN) : false;
        $project->link = !empty($incomingProject->link) ? htmlspecialchars($incomingProject->link) : '';
        if (isset($incomingProject->options)) {
            foreach ($incomingProject->options as $option => $value) {
                if (!$value instanceof stdClass) {
                    $option = htmlspecialchars(strip_tags(trim($value)));
                }
            }
            $project->options = $incomingProject->options;
        }
        return $project;
    }

    private function checkForProjectErrors($project)
    {
        $errors = [];
        if (empty($project->code)) {
            $errors[] = ['errorType' => 'missingCode'];
        }
        if (empty($project->name)) {
            $errors[] = ['errorType' => 'missingName'];
        }
        if (!is_bool($project->codeManuallyModified) ) {
            $errors[] = ['errorType'=>'codeManuallyModifiedNotBoolean'];
        }
        if (!is_bool($project->public)) {
            $errors[] = ['errorType' => 'publicNotBoolean'];
        }
        if (empty($project->link)) {
            $errors[] = ['errorType' => 'missingLink'];
        }
        return $errors;
    }

    private function returnIpAddress() {
        $ip = null;
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } else if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }
}
