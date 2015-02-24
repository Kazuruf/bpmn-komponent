<?php

/*
 * This file is part of KoolKode BPMN Komponent.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\BPMN\Komponent\Api;

use KoolKode\BPMN\History\HistoryService;
use KoolKode\BPMN\Repository\DeployedResource;
use KoolKode\BPMN\Repository\Deployment;
use KoolKode\BPMN\Repository\ProcessDefinition;
use KoolKode\BPMN\Repository\RepositoryService;
use KoolKode\BPMN\Runtime\Execution;
use KoolKode\BPMN\Runtime\ExecutionInterface;
use KoolKode\BPMN\Runtime\RuntimeService;
use KoolKode\BPMN\Task\TaskInterface;
use KoolKode\BPMN\Task\TaskService;
use KoolKode\Http\Exception\NotFoundException;
use KoolKode\Http\Http;
use KoolKode\Http\HttpRequest;
use KoolKode\Http\HttpResponse;
use KoolKode\Http\Komponent\Rest\JsonEntity;
use KoolKode\Http\Komponent\Rest\Route;
use KoolKode\Http\Komponent\Router\UriGeneratorInterface;
use KoolKode\Util\UUID;

class EngineResource
{
	protected $uri;
	
	public function setUri(UriGeneratorInterface $uri)
	{
		$this->uri = $uri;
	}
	
	protected $repositoryService;

	public function setRepositoryService(RepositoryService $repositoryService)
	{
		$this->repositoryService = $repositoryService;
	}

	protected $runtimeService;

	public function setRuntimeService(RuntimeService $runtimeService)
	{
		$this->runtimeService = $runtimeService;
	}

	protected $taskService;

	public function setTaskService(TaskService $taskService)
	{
		$this->taskService = $taskService;
	}
	
	protected $historyService;
	
	public function setHistoryService(HistoryService $historyService)
	{
		$this->historyService = $historyService;
	}
	
	/**
	 * @Route("GET /deployments")
	 */
	public function listDeployments()
	{
		$deployments = $this->repositoryService->createDeploymentQuery()->findAll();
		
		$json = new HalJsonEntity([
			'count' => count($deployments),
			'_links' => [
				'self' => $this->uri->generate('../list-deployments'),
				'bpmn:deploy' => [
					$this->uri->generate('../deploy-archive')->toArray(['name' => 'Deploy Archive']),
					$this->uri->generate('../deploy-file')->toArray(['name' => 'Deploy Process File'])
				]
			],
			'_embedded' => [
				'deployments' => $deployments
			]
		]);
		
		$json->decorate(function(Deployment $deployment) {
			return [
				'_links' => [
					'self' => $this->uri->generate('../show-deployment', [
						'id' => $deployment->getId()
					])
				]
			];
		});
		
		return $json;
	}
	
	/**
	 * @Route("GET /deployments/{id}")
	 */
	public function showDeployment($id)
	{
		$deployment = $this->repositoryService->createDeploymentQuery()->deploymentId($id)->findOne();
		
		$json = new HalJsonEntity($deployment);
		
		$json->decorate(function(Deployment $deployment) {
			return [
				'_links' => [
					'self' => $this->uri->generate('../show-deployment', ['id' => $deployment->getId()])
				],
				'_embedded' => [
					'resources' => array_values($deployment->findResources())
				]
			];
		});
		
		$json->decorate(function(DeployedResource $resource) use($id) {
			return [
				'_links' => [
					'self' => $this->uri->generate('../show-resource', [
						'id' => $resource->getDeployment()->getId(),
						'path' => explode('/', $resource->getName())
					])->toArray(['title' => 'Show resource meta data'])
				]
			];
		});
		
		return $json;
	}
	
	/**
	 * @Route("/deployments/{id}/resources{/path*}")
	 */
	public function showResource($id, $path)
	{
		$deployment = $this->repositoryService->createDeploymentQuery()->deploymentId($id)->findOne();
		$resources = $deployment->findResources();
		
		if(empty($resources[$path]))
		{
			throw new NotFoundException();
		}
		
		$json = new HalJsonEntity($resources[$path]);
		
		$json->decorate(function(DeployedResource $resources) {
			return [
				'_links' => [
					'self' => $this->uri->generate('../show-resource', [
						'id' => $resources->getDeployment()->getId(),
						'path' => explode('/', $resources->getName())
					])
				]
			];
		});
		
		return $json;
	}
	
	/**
	 * @Route("POST /deployments/file")
	 */
	public function deployFile(HttpRequest $request, $name = '')
	{
		if(!$request->hasEntity())
		{
			throw new \RuntimeException('No file has been uploaded');
		}
		
		$builder = $this->repositoryService->createDeployment($name);
		$builder->addResource('process.bpmn', $request->getEntity()->getInputStream());
		
		$deployment = $this->repositoryService->deploy($builder);
		$definitions = $this->repositoryService->createProcessDefinitionQuery()->deploymentId($deployment->getId())->findAll();
		
		$response = new HttpResponse(Http::CODE_CREATED);
		$response->setHeader('Location', $this->uri->generate('../show-deployment', [
			'id' => $deployment->getId()
		]));
		$response->setEntity(new JsonEntity([
			'deployment' => $deployment,
			'definitions' => $definitions,
			'resources' => array_values($deployment->findResources()),
			'_links' => [
				'self' => $this->uri->generate('../show-deployment', ['id' => $deployment->getId()])
			]
		]));
		
		return $response;
	}
	
	/**
	 * @Route("POST /deployments/archive")
	 */
	public function deployArchive(HttpRequest $request, $name = '', array $extensions = [])
	{
		if(!$request->hasEntity())
		{
			throw new \RuntimeException('No file has been uploaded');
		}
		
		$builder = $this->repositoryService->createDeployment($name);
		$builder->addExtensions($extensions);
		
		$in = $request->getEntity()->getInputStream();
		$archive = tempnam(sys_get_temp_dir(), 'era');
		$fp = fopen($archive, 'wb');
		
		try
		{
			while(false !== ($chunk = $in->read()))
			{
				fwrite($fp, $chunk);
			}
		}
		finally
		{
			@fclose($fp);
		}
		
		$builder->addArchive($archive);
		
		$deployment = $this->repositoryService->deploy($builder);
		$definitions = $this->repositoryService->createProcessDefinitionQuery()->deploymentId($deployment->getId())->findAll();
		
		$response = new HttpResponse(Http::CODE_CREATED);
		$response->setHeader('Location', $this->uri->generate('../show-deployment', [
			'id' => $deployment->getId()
		]));
		$response->setEntity(new JsonEntity([
			'deployment' => $deployment,
			'definitions' => $definitions,
			'resources' => array_values($deployment->findResources()),
			'_links' => [
				'self' => $this->uri->generate('../show-deployment', ['id' => $deployment->getId()])
			]
		]));
		
		return $response;
	}

	/**
	 * @Route("GET /definitions")
	 */
	public function listDefinitions()
	{
		$definitions = $this->repositoryService->createProcessDefinitionQuery()->findAll();
		
		$json = new HalJsonEntity([
			'count' => count($definitions),
			'_links' => [
				'self' => $this->uri->generate('../list-definitions'),
				'bpmn:message' => $this->uri->generate('../start-process-by-message')->toArray([
					'title' => 'Start a process instance using a message'
				])
			],
			'_embedded' => [
				'definitions' => $definitions
			]
		]);
		
		$json->decorate(function(ProcessDefinition $def) {
			return [
				'_links' => [
					'self' => $this->uri->generate('../show-definition', ['id' => $def->getId()])
				]
			];	
		});
		
		return $json;
	}

	/**
	 * @Route("GET /definitions/{id}")
	 */
	public function showDefinition($id)
	{
		$query = $this->repositoryService->createProcessDefinitionQuery()->processDefinitionId($id);
		$def = $query->findOne();
		
		$json = new HalJsonEntity($def);
		
		$json->decorate(function(ProcessDefinition $def) {
			return [
				'_links' => [
					'self' => $this->uri->generate('../show-definition', ['id' => $def->getId()]),
					'bpmn:start' => $this->uri->generate('../start-process', [
						'id' => $def->getId()
					])->toArray(['title' => 'Start an instance of the process']),
					'bpmn:diagram' => $this->uri->generate('../show-process-diagram', [
						'id' => $def->getId()
					])->toArray(['title' => 'Show BPMN 2.0 process diagram'])
				]
			];
		});
		
		return $json;
	}

	/**
	 * @Route("GET /definitions/{id}/diagram")
	 */
	public function showProcessDiagram($id)
	{
		$def = $this->repositoryService->createProcessDefinitionQuery()->processDefinitionId($id)->findOne();
		
		$deploymentId = $def->getDeploymentId();
		$resourceId = $def->getResourceId();
		
		if($resourceId === NULL)
		{
			throw new NotFoundException();
		}
		
		$deployment = $this->repositoryService->createDeploymentQuery()->deploymentId($deploymentId)->findOne();
		$diagram = NULL;
		
		foreach($deployment->findResources() as $resource)
		{
			if($resource->getId() == $resourceId)
			{
				$diagram = $resource;
				
				break;
			}
		}
		
		if($diagram === NULL)
		{
			throw new NotFoundException();
		}
		
		$response = new HttpResponse();
		$response->setHeader('Content-Type', 'application/xml');
		$response->setEntity((string)$resource->getContents());
		
		return $response;
	}
	
	/**
	 * @Route("POST /definitions/{id}")
	 */
	public function startProcess($id, JsonEntity $input)
	{
		$input = $input->toArray();
		$businessKey = array_key_exists('businessKey', $input) ? (string)$input['businessKey'] : NULL;
		$vars = array_key_exists('variables', $input) ? $input['variables'] : [];

		$def = $this->repositoryService->createProcessDefinitionQuery()->processDefinitionId($id)->findOne();
		$execution = $this->runtimeService->startProcessInstance($def, $businessKey, $vars);

		$response = new HttpResponse(Http::CODE_CREATED);
		$response->setHeader('Location', $this->uri->generate('../show-execution', [
			'id' => $execution->getId()
		]));
		$response->setEntity(new JsonEntity([
			'execution' => $execution,
			'variables' => $this->runtimeService->getExecutionVariables($execution->getId()),
			'_links' => [
				$this->createExecutionLinks($execution->getId())
			]
		]));

		return $response;
	}
	
	/**
	 * @Route("POST /definitions/message/{message}")
	 */
	public function startProcessByMessage($message, JsonEntity $input)
	{
		$input = $input->toArray();
		$businessKey = array_key_exists('businessKey', $input) ? (string)$input['businessKey'] : NULL;
		$vars = array_key_exists('variables', $input) ? $input['variables'] : [];
		
		$execution = $this->runtimeService->startProcessInstanceByMessage($message, $businessKey, $vars);
	
		$response = new HttpResponse(Http::CODE_CREATED);
		$response->setHeader('Location', $this->uri->generate('../show-execution', [
				'id' => $execution->getId()
		]));
		$response->setEntity(new JsonEntity([
				'execution' => $execution,
				'variables' => $this->runtimeService->getExecutionVariables($execution->getId()),
				'_links' => [
					$this->createExecutionLinks($execution->getId())
				]
		]));
	
		return $response;
	}
	
	protected function createExecutionLinks(UUID $executionId)
	{
		return [
			'self' => $this->uri->generate('../show-execution', ['id' => $executionId]),
			'bpmn:message' => $this->uri->generate('../send-message', ['id' => $executionId]),
			'bpmn:signal' => $this->uri->generate('../send-signal', ['id' => $executionId])
		];
	}

	/**
	 * @Route("GET /executions")
	 */
	public function listExecutions()
	{
		$executions = $this->runtimeService->createExecutionQuery()->findAll();
		
		$json = new HalJsonEntity([
			'count' => count($executions),
			'_links' => [
				'self' => $this->uri->generate('../list-executions'),
				'bpmn:signal' => $this->uri->generate('../broadcast-signal')->toArray([
					'title' => 'Broadcast a signal to all listening processes and executions'
				])
			],
			'_embedded' => [
				'executions' => $executions
			]
		]);
		
		$json->decorate(function(Execution $execution) {
			return [
				'_links' => [
					'self' => $this->uri->generate('../show-execution', ['id' => $execution->getId()])
				]
			];
		});
		
		return $json;
	}

	/**
	 * @Route("GET /executions/{id}")
	 */
	public function showExecution($id)
	{
		$execution = $this->runtimeService->createExecutionQuery()->executionId($id)->findOne();
		
		$json = new HalJsonEntity($execution);
		
		$json->decorate(function(Execution $execution) {
			return [
				'_links' => [
					$this->createExecutionLinks($execution->getId())
				],
				'_embedded' => [
					'variables' => $this->runtimeService->getExecutionVariables($execution->getId())
				]
			];
		});
		
		return $json;
	}
	
	/**
	 * @Route("GET /process/instance/{id}/activities")
	 */
	public function listProcessActivities($id)
	{
		$activities = $this->historyService->createHistoricActivityInstanceQuery()->processInstanceId($id)->canceled(false)->orderByStartedAt()->findAll();
	
		if(empty($activities))
		{
			throw new NotFoundException();
		}
		
		$json = new HalJsonEntity([
			'count' => count($activities),
			'_links' => [
				'self' => $this->uri->generate('../list-process-activities')
			],
			'_embedded' => [
				'activities' => $activities
			]
		]);
	
		return $json;
	}
	
	/**
	 * @Route("POST /executions/{id}/message/{message}")
	 */
	public function sendMessage($id, $message, JsonEntity $input)
	{
		$execution = $this->runtimeService->createExecutionQuery()->executionId($id)->findOne();
		$vars = $input->toArray();
	
		$this->runtimeService->messageEventReceived($message, $execution->getId(), $vars);
		
		return new JsonEntity([
			'_links' => [
				'bpmn:execution' => $this->uri->generate('../show-execution', ['id' => $execution->getId()])
			]
		]);
	}

	/**
	 * @Route("GET /subscriptions/message/{name}")
	 */
	public function listMessageSubscriptions($name)
	{
		$query = $this->runtimeService->createExecutionQuery()->messageEventSubscriptionName($name);
		$executions = $query->findAll();
		
		$json = new HalJsonEntity([
			'count' => count($executions),
			'_links' => [
				'self' => $this->uri->generate('../list-message-subscriptions', ['name' => $name])
 			],
			'_embedded' => [
				'executions' => $executions
			]
		]);
		
		$json->decorate(function(Execution $execution) {
			return [
				'_links' => $this->createExecutionLinks($execution->getId())
			];
		});
		
		return $json;
	}

	/**
	 * @Route("GET /subscriptions/signal/{name}")
	 */
	public function listSignalSubscriptions($name)
	{
		$query = $this->runtimeService->createExecutionQuery()->signalEventSubscriptionName($name);
		$executions = $query->findAll();
		
		$json = new HalJsonEntity([
			'count' => count($executions),
			'_links' => [
				'self' => $this->uri->generate('../list-signal-subscriptions', ['name' => $name])
			],
			'_embedded' => [
				'executions' => $executions
			]
		]);
		
		$json->decorate(function(Execution $execution) {
			return [
				'_links' => $this->createExecutionLinks($execution->getId())
			];
		});
		
		return $json;
	}
	
	/**
	 * @Route("POST /subscriptions/signal/{signal}")
	 */
	public function broadcastSignal($signal, JsonEntity $input)
	{
		$this->runtimeService->signalEventReceived($signal, NULL, $input->toArray());
	}
	
	/**
	 * @Route("POST /executions/{id}/signal/{signal}")
	 */
	public function sendSignal($id, $signal, JsonEntity $input)
	{
		$execution = $this->runtimeService->createExecutionQuery()->executionId($id)->signalEventSubscriptionName($signal)->findOne();
		$vars = $input->toArray();
		
		$this->runtimeService->signalEventReceived($signal, $execution->getId(), $vars);
		
		return new JsonEntity([
			'_links' => [
				'bpmn:execution' => $this->uri->generate('../show-execution', ['id' => $execution->getId()])
			]
		]);
	}

	/**
	 * @Route("GET /tasks")
	 */
	public function listTasks()
	{
		$tasks = $this->taskService->createTaskQuery()->findAll();
		
		$json = new HalJsonEntity([
			'count' => count($tasks),
			'_links' => [
				'self' => $this->uri->generate('../list-tasks')
			],
			'_embedded' => [
				'tasks' => $tasks
			]
		]);
		
		$json->decorate(function(TaskInterface $task) {
			return [
				'_links' => [
					'self' => $this->uri->generate('../show-task', ['id' => $task->getId()])
				]
			];
		});
		
		return $json;
	}

	/**
	 * @Route("GET /tasks/{id}")
	 */
	public function showTask($id)
	{
		$task = $this->taskService->createTaskQuery()->taskId($id)->findOne();
		
		$json = new HalJsonEntity($task);
		
		$json->decorate(function(TaskInterface $task) {
			return [
				'_links' => [
					'bpmn:complete' => $this->uri->generate('../complete-task', ['id' => $task->getId()]),
					'bpmn:execution' => $this->uri->generate('../show-execution', ['id' => $task->getExecutionId()])
				],
				'_embedded' => [
					'variables' => $this->runtimeService->getExecutionVariables($task->getExecutionId())
				]
			];
		});
		
		return $json;
	}

	/**
	 * @Route("POST /tasks/{id}")
	 */
	public function completeTask($id, JsonEntity $input)
	{
		$vars = $input->toArray();
		$task = $this->taskService->createTaskQuery()->taskId($id)->findOne();

		$this->taskService->complete($task->getId(), $vars);

		try
		{
			$vars = $this->runtimeService->getExecutionVariables($task->getExecutionId());
		}
		catch(\OutOfBoundsException $e)
		{
			$vars = [];
		}
		
		return new JsonEntity([
			'task' => $task,
			'variables' => $vars,
			'_links' => [
				'execution' => $this->uri->generate('../show-execution', ['id' => $task->getExecutionId()])
			]
		]);
	}
}

