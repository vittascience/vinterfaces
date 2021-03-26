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
                foreach ($data['idUsers'] as $idUser) {
                    $user = $this->entityManager->getRepository('User\Entity\User')
                        ->find($idUser);
                    $project = $this->entityManager->getRepository('Interfaces\Entity\Project')
                        ->findOneBy(array('link' => $data['linkProject']));
                    $projectLinkUser = new ProjectLinkUser($user, $project);
                    $this->entityManager->persist($projectLinkUser);
                    $this->entityManager->flush();
                }
                return $project;
            }
        );
    }
}
