<?php

namespace Interfaces\Controller;

use Interfaces\Entity\ProjectLinkUser;

class ControllerProjectLinkUser extends Controller
{
    public function __construct($entityManager, $user)
    {
        parent::__construct($entityManager, $user);
        $this->actions = array(
            'get_all' => function ($data) {
                return $this->entityManager->getRepository('Interfaces\Entity\ProjectLinkUser')
                    ->findBy(array("deleted" => false, "interface" => $data['interface']));
            },
            'add' => function ($data) {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "addNotRetrievedNotAuthenticated"];

                // initialize, bind and sanitize data
                $errors = [];
                $projectLink = !empty($_POST['linkProject']) ? htmlspecialchars(strip_tags(trim($_POST['linkProject']))) : '';
                if(!$projectLink){
                    array_push($errors, array('errorType'=> 'projectLinkInvalid'));
                    return array('errors'=> $errors);
                }
                // get the project from db
                $project = $this->entityManager->getRepository('Interfaces\Entity\Project')
                    ->findOneBy(array('link' => $projectLink));

                if(!$project){
                    array_push($errors, array('errorType'=>'projectNotFound'));
                    return array('errors'=> $errors);
                }

                
                foreach ($data['idUsers'] as $idUser) {
                    $currentUserId = intval($idUser);
                    
                    $user = $this->entityManager->getRepository('User\Entity\User')
                        ->find($currentUserId);

                    // get record from db
                    $projectLinUserFound = $this->entityManager->getRepository(ProjectLinkUser::class)->findOneBy(array(
                        'user'=> $user,
                        'project'=> $project
                    ));

                    // no record found, we can insert it
                    if(!$projectLinUserFound){
                        $projectLinkUser = new ProjectLinkUser($user, $project);
                        $this->entityManager->persist($projectLinkUser);
                        $this->entityManager->flush();
                    }
                    
                }
                return $project;
            }
        );
    }
}
