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

use KoolKode\BPMN\Repository\RepositoryService;
use KoolKode\BPMN\Runtime\ExecutionInterface;
use KoolKode\BPMN\Runtime\RuntimeService;
use KoolKode\BPMN\Task\TaskService;
use KoolKode\Http\Http;
use KoolKode\Http\HttpRequest;
use KoolKode\Http\HttpResponse;
use KoolKode\Rest\JsonEntity;
use KoolKode\Rest\Route;
use KoolKode\Router\UriGeneratorInterface;

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
	
	/**
	 * @Route("GET /deployments")
	 */
	public function listDeployments()
	{
		return new JsonEntity([
			'deployments' => $this->repositoryService->createDeploymentQuery()->findAll(),
			'_links' => [
				'details' => $this->uri->generate('../show-deployment'),
				'deploy-archive' => $this->uri->generate('../deploy-archive'),
				'deploy-file' => $this->uri->generate('../deploy-file')
			]
		]);
	}
	
	/**
	 * @Route("GET /deployments/{id}")
	 */
	public function showDeployment($id)
	{
		$deployment = $this->repositoryService->createDeploymentQuery()->deploymentId($id)->findOne();
		
		return new JsonEntity([
			'deployment' => $deployment,
			'resources' => array_values($deployment->findResources())
		]);
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
				'details' => $this->uri->generate('../show-deployment', ['id' => $deployment->getId()])
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
			while($chunk = $in->read())
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
				'details' => $this->uri->generate('../show-deployment', ['id' => $deployment->getId()])
			]
		]));
		
		return $response;
	}

	/**
	 * @Route("GET /definitions")
	 */
	public function listDefinitions()
	{
		return new JsonEntity([
			'definitions' => $this->repositoryService->createProcessDefinitionQuery()->findAll(),
			'_links' => [
				'detail' => $this->uri->generate('../show-definition')
			]
		]);
	}

	/**
	 * @Route("GET /definitions/{id}")
	 */
	public function showDefinition($id)
	{
		$query = $this->repositoryService->createProcessDefinitionQuery()->processDefinitionId($id);
		$def = $query->findOne();
		
		return new JsonEntity([
			'definition' => $def,
			'_links' => [
				'start' => $this->uri->generate('../start-process', ['id' => $def->getId()])
			]
		]);
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
				$this->createExecutionLinks($execution)
			]
		]));

		return $response;
	}
	
	protected function createExecutionLinks(ExecutionInterface $execution)
	{
		return [
			'self' => $this->uri->generate('../show-execution', ['id' => $execution->getId()]),
			'message' => $this->uri->generate('../send-message', ['id' => $execution->getId()]),
			'signal' => $this->uri->generate('../send-signal', ['id' => $execution->getId()])
		];
	}

	/**
	 * @Route("GET /executions")
	 */
	public function listExecutions()
	{
		return new JsonEntity([
			'executions' => $this->runtimeService->createExecutionQuery()->findAll(),
			'_links' => [
				'signal' => $this->uri->generate('../broadcast-signal')
			]
		]);
	}

	/**
	 * @Route("GET /executions/{id}")
	 */
	public function showExecution($id)
	{
		$execution = $this->runtimeService->createExecutionQuery()->executionId($id)->findOne();

		return new JsonEntity([
			'execution' => $execution,
			'variables' => $this->runtimeService->getExecutionVariables($execution->getId()),
			'_links' => [
				$this->createExecutionLinks($execution)
			]
		]);
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
				'execution' => $this->uri->generate('../show-execution', ['id' => $execution->getId()])
			]
		]);
	}

	/**
	 * @Route("GET /subscriptions/message/{name}")
	 */
	public function listMessageSubscriptions($name)
	{
		$query = $this->runtimeService->createExecutionQuery()->messageEventSubscriptionName($name);

		return new JsonEntity([
			'executions' => $query->findAll()
		]);
	}

	/**
	 * @Route("GET /subscriptions/signal/{name}")
	 */
	public function listSignalSubscriptions($name)
	{
		$query = $this->runtimeService->createExecutionQuery()->signalEventSubscriptionName($name);

		return new JsonEntity([
			'executions' => $query->findAll()
		]);
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
				'execution' => $this->uri->generate('../show-execution', ['id' => $execution->getId()])
			]
		]);
	}

	/**
	 * @Route("GET /tasks")
	 */
	public function listTasks()
	{
		return new JsonEntity([
			'tasks' => $this->taskService->createTaskQuery()->findAll()
		]);
	}

	/**
	 * @Route("GET /tasks/{id}")
	 */
	public function showTask($id)
	{
		$task = $this->taskService->createTaskQuery()->taskId($id)->findOne();
		
		return new JsonEntity([
			'task' => $task,
			'variables' => $this->runtimeService->getExecutionVariables($task->getExecutionId()),
			'_links' => [
				'complete' => $this->uri->generate('../complete-task', ['id' => $task->getId()]),
				'execution' => $this->uri->generate('../show-execution', ['id' => $task->getExecutionId()])
			]
		]);
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

