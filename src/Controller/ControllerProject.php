<?php

namespace Interfaces\Controller;

use User\Entity\User;
use Interfaces\Entity\Project;
use Interfaces\Entity\LtiProject;

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
                $projectJSON = json_decode($data['project']);
                if ($this->user['id'] == $projectJSON->user->id) {
                    $project = $this->entityManager->getRepository('Interfaces\Entity\Project')
                        ->findOneBy(array("link" => $projectJSON->link));
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
                $exercise = $this->entityManager->getRepository('Python\Entity\ExercisePython')
                    ->findOneBy(array("project" => $project->getId()));
                if ($exercise) {
                    return true;
                }
                return false;
            },
            'lti_teacher_duplicate_project'=>function() { 

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
                if(empty($ltiCourseId)) $errors['ltiCourseIdInvalid'] = true;
                if(empty($ltiResourceLinkId)) $errors['resourceLinkIdInvalid'] = true;
                if(empty($projectLink)) $errors['projectLinkInvalid'] = true;
 
                // some errors found, return them
                if(!empty($errors)){
                    return array('errors'=>$errors);
                }
               
                // get the user
                $user = $this->entityManager
                                ->getRepository(User::class)->find($userId);
                                
                // get the project                        
                $project = $this->entityManager
                            ->getRepository('Interfaces\Entity\Project')
                            ->findOneBy(array("link" => $projectLink));

                // the project does not exists, return an error
                if(!$project){
                    return array('errorType'=> 'projectNotFoundWithProvidedLink');
                }

                // get the lti project from interfaces_lti_project table
                $ltiProjectNotAlreadySubmitted = $this->entityManager
                                    ->getRepository(LtiProject::class)
                                    ->findOneBy(array(
                                        'user' => $user->getId(),
                                        'ltiCourseId' => $ltiCourseId,
                                        'ltiResourceLinkId' => $ltiResourceLinkId,
                                        'isSubmitted' => 0
                                    ));

                // no reference of this project in ltiProject ($user + $courseId + $ltiResourceId do not exists)
                if(!$ltiProjectNotAlreadySubmitted){
                    
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
                    $this->entityManager->persist($projectDuplicated);
                    $this->entityManager->flush();

                    // we create a ltiProject entry in interfaces_lti_projects and save it
                    $ltiProject = new LtiProject();
                    $ltiProject->setUser($user);
                    $ltiProject->setUserProjectLink($projectDuplicated->getLink());
                    $ltiProject->setLtiCourseId($ltiCourseId);
                    $ltiProject->setLtiResourceLinkId($ltiResourceLinkId);
                    $ltiProject->setIsSubmitted(false);

                    $this->entityManager->persist($ltiProject);
                    $this->entityManager->flush();

                    // save data in session
                    $_SESSION['lti_project_id'] = $ltiProject->getId();
                    return  $projectDuplicated->jsonSerialize();
                }

                $userProject = $this->entityManager
                            ->getRepository(Project::class)
                            ->findOneByLink($ltiProjectNotAlreadySubmitted->getUserProjectLink());
                
                if(!$userProject){
                    return array('errorType'=> "userProjectNotFound");
                }

                // save data in session
                $_SESSION['lti_project_id'] = $ltiProjectNotAlreadySubmitted->getId();
                return $userProject;
                
            }
        );
    }
}
