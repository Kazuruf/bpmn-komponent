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
	protected $uriGenerator;
	
	public function setUriGenerator(UriGeneratorInterface $uriGenerator)
	{
		$this->uriGenerator = $uriGenerator;
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
	 * @Route("GET /definitions")
	 */
	public function listDefinitions()
	{
		return new JsonEntity([
			'definitions' => $this->repositoryService->createProcessDefinitionQuery()->findAll(),
			'_links' => [
				'deploy' => $this->uriGenerator->generate('../deploy-diagram')
			]
		]);
	}
	
	/**
	 * @Route("POST /definitions")
	 */
	public function deployDiagram(HttpRequest $request)
	{
		if(!$request->hasEntity())
		{
			throw new \RuntimeException('No BPMN 2.0 process or collaboration diagram uploaded');
		}
		
		$def = $this->repositoryService->deployDiagram($request->getEntity()->getInputStream());
		
		$response = new HttpResponse(Http::CODE_CREATED);
		$response->setHeader('Location', $this->uriGenerator->generate('../show-definition', ['id' => $def->getId()]));
		$response->setEntity(new JsonEntity([
			'definition' => $def,
			'_links' => [
				'start' => $this->uriGenerator->generate('../start-process', ['id' => $def->getId()])
			]
		]));
		
		return $response;
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
				'start' => $this->uriGenerator->generate('../start-process', ['id' => $def->getId()])
			]
		]);
	}
	
	protected function createExecutionLinks(ExecutionInterface $execution)
	{
		return [
			'self' => $this->uriGenerator->generate('../show-execution', ['id' => $execution->getId()]),
			'message' => $this->uriGenerator->generate('../send-message', ['id' => $execution->getId()]),
			'signal' => $this->uriGenerator->generate('../send-signal', ['id' => $execution->getId()])
		];
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
		$response->setHeader('Location', $this->uriGenerator->generate('../show-execution', [
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

	/**
	 * @Route("GET /executions")
	 */
	public function listExecutions()
	{
		return new JsonEntity([
			'executions' => $this->runtimeService->createExecutionQuery()->findAll(),
			'_links' => [
				'signal' => $this->uriGenerator->generate('../broadcast-signal')
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
				'execution' => $this->uriGenerator->generate('../show-execution', ['id' => $execution->getId()])
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
				'execution' => $this->uriGenerator->generate('../show-execution', ['id' => $execution->getId()])
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
				'complete' => $this->uriGenerator->generate('../complete-task', ['id' => $task->getId()]),
				'execution' => $this->uriGenerator->generate('../show-execution', ['id' => $task->getExecutionId()])
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
				'execution' => $this->uriGenerator->generate('../show-execution', ['id' => $task->getExecutionId()])
			]
		]);
	}
}

