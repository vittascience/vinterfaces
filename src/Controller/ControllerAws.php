<?php

namespace Interfaces\Controller;

use DateTime;
use DAO\RegularDAO;
use User\Entity\User;
use Aws\Ec2\Ec2Client;
use Aws\Ecs\EcsClient;
use GuzzleHttp\Client;
use function Aws\filter;
use Interfaces\Controller\Controller;
use Interfaces\Entity\PythonWhitelist;

use Interfaces\Entity\PythonContainers;
use Aws\ElasticLoadBalancingV2\ElasticLoadBalancingV2Client;

class ControllerAws extends Controller
{
    protected $client;
    protected $isAdmin;
    protected $idPremium;
    protected $WhiteList;

    public function __construct($entityManager, $user)
    {
        parent::__construct($entityManager, $user);
        // initialize the ECS client
        $this->client = new EcsClient([
            'version' => 'latest',
            'region' => $_ENV['VS_AWS_REGION'],
            'credentials' => [
                'key' => $_ENV['VS_AWS_KEY'],
                'secret' => $_ENV['VS_AWS_SECRET']
                ]
        ]);
            
            
        // initialize the EC2 client
        $this->clientEc2 = new Ec2Client([
            'version' => 'latest',
            'region' => $_ENV['VS_AWS_REGION'],
            'credentials' => [
                'key' => $_ENV['VS_AWS_KEY'],
                'secret' => $_ENV['VS_AWS_SECRET']
                ]
        ]);
                
        // initialize the ELB client
        $this->clientElb = new ElasticLoadBalancingV2Client([
            'version' => 'latest',
            'region' => $_ENV['VS_AWS_REGION'],
            'credentials' => [
                'key' => $_ENV['VS_AWS_KEY'],
                'secret' => $_ENV['VS_AWS_SECRET']
                ]
        ]);
                    
                    
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['id' => intval($_SESSION["id"])]) ?? null;
        $this->whiteListedRule = "arn:aws:elasticloadbalancing:eu-west-3:342829420373:listener-rule/app/VittascienceALB/d5218bfa7cdf4cd7/280e909132d6a679/7ad2e27090004e69";
        $this->WhiteList = $this->entityManager->getRepository(PythonWhitelist::class)->findAll();

        if (isset($_SESSION["id"])) {
            $currentUserId = intval($_SESSION["id"]);
            $this->isPremium = RegularDAO::getSharedInstance()->isTester($currentUserId);
            $this->isAdmin = RegularDAO::getSharedInstance()->isAdmin($currentUserId);
        } else {
            $this->isPremium = false;
            $this->isAdmin = false;
        }

        $this->actions = array(
            'runTask' => function () {

                // Launch task function
                $result = $this->client->runTask([
                    'cluster' => $_ENV['VS_AWS_CLUSTER'],
                    'count' => 1,
                    'enableECSManagedTags' => true,
                    'enableExecuteCommand' => true,
                    'launchType' => 'FARGATE',
                    'networkConfiguration' => [
                        'awsvpcConfiguration' => [
                            'assignPublicIp' => 'ENABLED',
                            'securityGroups' => [$_ENV['VS_AWS_SECURITY_GROUP']],
                            'subnets' => [$_ENV['VS_AWS_SUBNET']],
                        ],
                    ],
                    'overrides' => [
                        'taskRoleArn' => 'arn:aws:iam::342829420373:role/ecsTaskExecutionRole'
                    ],
                    'taskDefinition' => $_ENV['VS_AWS_TASK_DEFINITION'],
                ])->toArray();

                $Container = new PythonContainers();
                $Container->setTaskArn($result["tasks"][0]["taskArn"]);
                $Container->setCreatedAt($result["tasks"][0]["createdAt"]);
                $Container->setUser($this->user);
                $Container->setLink($this->createAlbLink());
                $Container->setStatus($result["tasks"][0]["lastStatus"]);
                $this->entityManager->persist($Container);
                $this->entityManager->flush();

                return true;
            },
            "stopTask" => function ($data) {
                // Stop task function
                if ($this->isAdmin || $this->isPremium) {
                    $taskId = $data['arnTask'];
                    $container = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['taskArn' => $taskId]);

                    $result = $this->client->stopTask([
                        'cluster' => $_ENV['VS_AWS_CLUSTER'],
                        'task' => $taskId
                    ])->toArray();

                    if ($container) {
                        $this->action('autoClean');
                        $this->entityManager->remove($container);
                    }

                    $this->entityManager->flush();
                    return true;
                } else {
                    return ["auth" => false, "response" => "forbidden"];
                }
            },
            "listTasks" => function () {
                if ($this->isAdmin) {
                    $containers = $this->entityManager->getRepository(PythonContainers::class)->findAll();
                    return $containers;
                } else {
                    return ["auth" => false, "response" => "forbidden"];
                }
            },
            "stopAllTasks" => function () {
                // Stop all the running tasks
                if ($this->isAdmin) {
                    $arr = [];
                    $result = $this->client->listTasks(['cluster' => $_ENV['VS_AWS_CLUSTER']])->toArray();
                    foreach ($result['taskArns'] as $task) {
                        $taskId = explode("/", $task)[2];
                        $container = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['taskArn' => $task]);
                        if ($container) {
                            $arr[] = $result = $this->client->stopTask(['cluster' => $_ENV['VS_AWS_CLUSTER'], 'task' => $taskId])->toArray();
                            $this->entityManager->remove($container);
                        }
                    }
                    $this->entityManager->flush();
                    $this->action('autoClean');
                    return $arr;
                } else {
                    return ["auth" => false, "response" => "forbidden"];
                }
            },
            "getFreeContainer" => function () {
                $accessKey = htmlspecialchars($_POST['accessKey']);
                $domainReferer = parse_url($_POST['referer']);
                $isExerciceWhitelist = false;
                $queryParsed = explode("&", $domainReferer["query"]);

                // ID whitelist check
                $link = null;
                foreach ($queryParsed as $queryString) {
                    if (str_contains($queryString, "link")) {
                        $link = explode("=", $queryString)[1];
                    }
                }

                if ($link) {
                    foreach ($this->WhiteList as $referer) {
                        if ($referer->getExerciceId() == $link) {
                            $isExerciceWhitelist = true;
                            break;
                        }
                    }
                }

                // Check if the referer is white listed
                if (!$this->user && !$isExerciceWhitelist) {
                    return ["auth" => false, "response" => "notLogged"];
                }
                // If the user is not from a whitelisted domaine, he need to be authenticated and premium or admin
                if (!$this->isPremium && !$this->isAdmin && !$isExerciceWhitelist) {
                    return ["auth" => false, "response" => "notPremium"];
                }
                // We need a method to authenticated the user
                if (!$this->user && !$accessKey) {
                    return ["auth" => false, "response" => "noCredentials"];
                }

                $alreadyLinkedToContainer = false;
                if (!empty($accessKey)) {
                    $alreadyLinkedToContainer = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['accessKey' => $accessKey]);
                } else if ($this->user) {
                    $alreadyLinkedToContainer = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['isAttribued' => $this->user]);
                }

                if ($alreadyLinkedToContainer) {
                    return $alreadyLinkedToContainer->getLink();
                } else {
                    $freeContainer = $this->entityManager->getRepository(PythonContainers::class)->getFreeContainer();
                    // if there is no free container
                    if (count($freeContainer) == 0) {
                        $alreadyAskedForContainer = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['user' => $this->user]);
                        if (!$alreadyAskedForContainer) {
                            $this->action('runTask');
                            return ["message" => "noContainer"];
                        } else if ($alreadyAskedForContainer && !$alreadyLinkedToContainer) {
                            return ["message" => "waitingContainer"];
                        } else {
                            return ["message" => "onlyOneContainer"];
                        }
                    }
                    if ($this->user) {
                        $freeContainer[0]->setisAttribued($this->user);
                    }

                    if ($accessKey) {
                        $freeContainer[0]->setAccessKey($accessKey);
                    }

                    $this->entityManager->persist($freeContainer[0]);
                    $this->entityManager->flush();
                    // launch new container to prevent to be out of free container
                    $this->action('runTask');
                    return $freeContainer[0]->getLink();
                }
            },
            "runMultipleTasks" => function ($data) {
                if ($this->isAdmin) {
                    $taskNumber = (int)htmlspecialchars($data["number"]);
                    // Launch task function
                    $result = $this->client->runTask([
                        'cluster' => $_ENV['VS_AWS_CLUSTER'],
                        'count' => $taskNumber,
                        'enableECSManagedTags' => true,
                        'enableExecuteCommand' => true,
                        'launchType' => 'FARGATE',
                        'networkConfiguration' => [
                            'awsvpcConfiguration' => [
                                'assignPublicIp' => 'ENABLED',
                                'securityGroups' => [$_ENV['VS_AWS_SECURITY_GROUP']],
                                'subnets' => [$_ENV['VS_AWS_SUBNET']],
                            ],
                        ],
                        'overrides' => [
                            'taskRoleArn' => 'arn:aws:iam::342829420373:role/ecsTaskExecutionRole'
                        ],
                        'taskDefinition' => $_ENV['VS_AWS_TASK_DEFINITION'],
                    ])->toArray();

                    foreach ($result["tasks"] as $task) {
                        $Container = new PythonContainers();
                        $Container->setTaskArn($task["taskArn"]);
                        $Container->setCreatedAt($task["createdAt"]);
                        $Container->setUser($this->user);
                        $Container->setLink($this->createAlbLink());
                        $Container->setStatus($task["lastStatus"]);
                        $this->entityManager->persist($Container);
                        $this->entityManager->flush();
                    }
                    return $result;
                }
            },
            "containerOnline" => function () {
                // When a new container become online he sent a notification on this action
                // Fetch all the tasks
                $result = $this->client->listTasks(
                    [
                        'cluster' => $_ENV['VS_AWS_CLUSTER'],
                        'desiredStatus' => 'RUNNING'
                    ]
                )->toArray();

                $arrayArn = [];
                foreach ($result["taskArns"] as $task) {
                    // Split the Arn to get the task id
                    $taskID = explode("/", $task);
                    $arrayArn[] = $taskID[2];
                }

                // Describe the tasks who're running
                $taskDescription = $this->client->DescribeTasks(["cluster" => $_ENV['VS_AWS_CLUSTER'], 'tasks' => $arrayArn])->toArray();
                foreach ($taskDescription["tasks"] as $task) {
                    if ($task['lastStatus'] == "RUNNING") {
                        $container = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['taskArn' => $task["taskArn"]]);
                        if ($container) {
                            $container->setStatus($task["lastStatus"]);
                        } else {
                            $container = new PythonContainers();
                            $container->setTaskArn($task["taskArn"]);
                            $container->setCreatedAt($task["createdAt"]);
                            $container->setStatus($task['lastStatus']);
                            $container->setLink($this->createAlbLink());
                        }
                        if ($this->user) {
                            $container->setisAttribued($this->user);
                        }
                        if ($container->getProcess() == 0) {
                            $container->setProcess(1);
                            $this->entityManager->persist($container);
                            $this->entityManager->flush();
                            $container = $this->getPrivateIpAndSetParameters($container, $task);
                        }     
                    }
                    $this->entityManager->persist($container);
                    $this->entityManager->flush();
                }
                return true;
            },
            'killContainer' => function ($data) {
                // Stop task function
                $containerKey = $data['containerKey'];
                if (!empty($containerKey)) {
                    $container = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['containerKey' => $containerKey]);
                    if ($container) {
                        $containerArn = $container->getTaskArn();
                        $taskID = explode("/", $containerArn);
                        $result = $this->client->stopTask([
                            'cluster' => $_ENV['VS_AWS_CLUSTER'],
                            'task' => $taskID[2]
                        ])->toArray();

                        // We need to delete target groups and rules link to this container
                        $this->deleteRule($container->getRuleVnc());
                        $this->deleteRule($container->getRuleNode());

                        $this->deleteTargetGroup($container->getTargetNode());
                        $this->deleteTargetGroup($container->getTargetVnc());

                        $this->entityManager->remove($container);
                        $this->entityManager->flush();
                        $this->cleanFunction();
                        return true;
                    }
                } else {
                    return ["success" => false, "response" => "forbidden"];
                }
            },
            'autoClean' => function () {
                // Check if all the container are running before cleaning
                $isContainerProvisioning = $this->entityManager->getRepository(PythonContainers::class)->findBy(['publicIp' => null]);
                // Check if the container is in the database
                // If the container is not running on aws, we delete it and his rules and target groups
                if (empty($isContainerProvisioning)) {
                    $rulesDeleted = $this->compareRules();
                    $targetsDeleted = $this->compareTargetGroups();
                    $containersDeleted = $this->compareContainers();
                    return ['success' => true, 'message' => 'Cleaned', 'rulesDeleted' => $rulesDeleted, 'targetsDeleted' => $targetsDeleted, 'containersDeleted' => $containersDeleted];
                } else {
                    $checkForUnRegistred = $this->entityManager->getRepository(PythonContainers::class)->findBy(['publicIp' => null, 'status' => "RUNNING"]);
                    if (!empty($checkForUnRegistred)) {
                        foreach ($checkForUnRegistred as $container) {
                            $container->setProcess(0);
                            $this->entityManager->persist($container);
                            $this->entityManager->flush();
                        }
                        $this->action('containerOnline');
                    }
                    return ['success' => false, 'message' => 'noErrors'];
                }
            },
            'checkIfExerciceIsServerOnly' => function() {

                $domainReferer = parse_url($_POST['urlData']);
                $queryParsed = explode("&", $domainReferer["query"]);

                // ID whitelist check
                $link = null;
                foreach ($queryParsed as $queryString) {
                    if (str_contains($queryString, "link")) {
                        $link = explode("=", $queryString)[1];
                    }
                }

                if ($link == null) {
                    return ['success' => false, 'message' => 'noIdProvided'];
                }

                foreach ($this->WhiteList as $element) {
                    if ($element->getExerciceId() == $link) {
                        if ($element->getServerOnly() == 1) {
                            return ['success' => true, 'message' => 'serverOnly'];
                        } else {
                            return ['success' => true, 'message' => 'notServerOnly'];
                        }
                    }
                };

                return ['success' => false, 'message' => 'noExerciceFound'];
            }
        );
    }

    private function getPrivateIpAndSetParameters(PythonContainers $container, array $taskDescription): PythonContainers
    {
        $container->setUpdatedAt(new DateTime('NOW'));

        // Ping the container on /getContainerKey to fetch the intern key and set it in the DB 
        $client = new Client(['verify' => false]);

        // Get the public ip of the container
        $eniId = $taskDescription['attachments'][0]['details'][1]['value'];

        // Get the detail of the network interface
        $networkInterface = $this->clientEc2->describeNetworkInterfaces([
            'NetworkInterfaceIds' => [$eniId,],
        ]);

        // Fetch the ip in the describe network interface and set it in the DB
        $publicIp = $networkInterface['NetworkInterfaces'][0]['Association']['PublicIp'];
        $privateIp = $taskDescription['containers'][0]['networkInterfaces'][0]['privateIpv4Address'];

        // Get the detail of the network interface
        $container->setPublicIp($publicIp);

        $containerKey = $client->request('POST', "https://$publicIp:3960/getContainerKey");

        $container->setContainerKey($containerKey->getBody());

        $exist = false;
        do {
            $priority_node = rand(1, 49999);
            $priority_vnc = rand(1, 49999);

            $allContainers = $this->entityManager->getRepository(PythonContainers::class)->findAll();
            if ($allContainers) {
                foreach ($allContainers as $Containers) {
                    $priorityCheck = $Containers->getPriority();
                    $arrayPriority = explode(",", $priorityCheck);
                    if (in_array($priority_node, $arrayPriority) || in_array($priority_vnc, $arrayPriority)) {
                        $exist = true;
                    }
                }
            }
        } while ($exist);

        $container->setPriority("$priority_node,$priority_vnc");
        $this->manageTargetsAndRules($privateIp, $container, $priority_node, $priority_vnc);
        return $container;
    }

    private function manageTargetsAndRules(String $privateIP, PythonContainers $container, $priorityN, $priorityV)
    {
        // need to store the target group arn in the DB
        $link = $container->getLink();

        $targetArnNode = $this->createTarget($link, $privateIP, 3960);
        $targetArnVnc = $this->createTarget($link, $privateIP, 6080);

        // Set the target group arn in the DB
        $container->setTargetNode($targetArnNode);
        $container->setTargetVnc($targetArnVnc);

        // Create the rules
        $ruleArnNode = $this->createRule($link, $targetArnNode, $priorityN, 3960);
        $ruleArnVnc = $this->createRule($link, $targetArnVnc, $priorityV, 6080);

        // Set the rule arn in the DB
        $container->setRuleNode($ruleArnNode);
        $container->setRuleVnc($ruleArnVnc);
    }

    private function deleteRule(?string $ruleArn)
    {
        return $this->clientElb->deleteRule([
            'RuleArn' => $ruleArn, // REQUIRED
        ]);
    }

    private function deleteTargetGroup(?string $targetGroupArn)
    {
        return $this->clientElb->deleteTargetGroup([
            'TargetGroupArn' => $targetGroupArn, // REQUIRED
        ]);
    }

    private function createAlbLink()
    {
        $ALPHANUMERIC = "abcdefghijklmnopqrstuvwxyz0123456789";
        $link = "";
        for ($i = 0; $i < 7; $i++) {
            $link .= substr($ALPHANUMERIC, rand(0, 35), 1);
        }
        return $link;
    }

    private function compareTargetGroups()
    {
        $targetsDeleted = [];
        $allTargets = $this->clientElb->describeTargetGroups()->toArray();
        if ($allTargets) {
            foreach ($allTargets['TargetGroups'] as $target) {
                $targetNode = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['targetNode' => $target['TargetGroupArn'], 'status' => 'running']);
                $targetVnc = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['targetVnc' => $target['TargetGroupArn'], 'status' => 'running']);

                if (!$targetNode && !$targetVnc) {
                    $this->deleteTargetGroup($target['TargetGroupArn']);
                    $targetsDeleted[] = $target['TargetGroupArn'];
                }
            }
        }
        return $targetsDeleted;
    }

    /**
     * compare rules in aws with the rules of vittascience and delete the rules that are not in vittascience
     */
    private function compareRules()
    {
        $rulesDeleted = [];
        $allRules = $this->getAllRules();
        if ($allRules) {
            foreach ($allRules['Rules'] as $rule) {
                if ($rule['RuleArn'] != $this->whiteListedRule) {
                    $ruleNode = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['ruleNode' => $rule['RuleArn'], 'status' => 'RUNNING']);
                    $ruleVnc = $this->entityManager->getRepository(PythonContainers::class)->findOneBy(['ruleVnc' => $rule['RuleArn'], 'status' => 'RUNNING']);
                    if (!$ruleNode && !$ruleVnc) {
                        $this->deleteRule($rule['RuleArn']);
                        $rulesDeleted[] = $rule['RuleArn'];
                    }
                }
            }
        }
        return $rulesDeleted;
    }

    private function compareContainers()
    {
        $containersDeleted = [];
        // In database
        $containers = $this->entityManager->getRepository(PythonContainers::class)->findAll();
        // In AWS
        $result = $this->client->listTasks(['cluster' => $_ENV['VS_AWS_CLUSTER']])->toArray();
        // Remove containers that are not in AWS
        foreach ($containers as $container) {
            $found = false;
            foreach ($result['taskArns'] as $task) {
                if ($container->getTaskArn() == $task) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $containersDeleted[] = $container->getLink();
                $this->entityManager->remove($container);
                $this->entityManager->flush();
            }
        }
        return $containersDeleted;
    }

    private function getAllRules()
    {
        return $this->clientElb->describeRules([
            'ListenerArn' => $_ENV['VS_AWS_LISTENER_GLOBAL'],
        ])->toArray();
    }

    private function cleanFunction()
    {
        $this->compareRules();
        $this->compareTargetGroups();
        $this->compareContainers();
    }

    private function createTarget($link, $privateIP, $port)
    { 
        $res = $this->clientElb->createTargetGroup([
            'Name' => "$link-$port", // REQUIRED
            'Port' => $port,
            'Protocol' => 'HTTPS',
            'TargetType' => 'ip',
            'VpcId' => $_ENV['VS_AWS_VPC_ID'],
        ]);

        $targetGroupArn = $res['TargetGroups'][0]['TargetGroupArn'];
        $this->clientElb->registerTargets([
            'TargetGroupArn' => $targetGroupArn, // REQUIRED
            'Targets' => [ // REQUIRED
                [
                    'Id' => $privateIP, // REQUIRED
                    'Port' => $port,
                ],
            ],
        ]);

        return $targetGroupArn;
    }

    private function createRule($link, $targetGroupArn, $priority, $port)
    {
        $value = $port == 3960 ?  $link . "n" : $link . "v";
        $res = $this->clientElb->createRule([
            'Actions' => [ // REQUIRED
                [
                    'TargetGroupArn' => $targetGroupArn,
                    'Type' => 'forward', // REQUIRED
                ],
            ],
            'Conditions' => [ // REQUIRED
                [
                    'Field' => 'query-string', // REQUIRED
                    'QueryStringConfig' => [
                        'Values' => [
                            [
                                'Key' => 'c',
                                'Value' => $value,
                            ],
                        ],
                    ],
                ],
            ],
            'ListenerArn' => $_ENV['VS_AWS_LISTENER_GLOBAL'], // REQUIRED
            'Priority' => $priority, // REQUIRED
        ]);

        return $res['Rules'][0]['RuleArn'];
    }
}
