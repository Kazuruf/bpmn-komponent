<?php

namespace KoolKode\BPMN\Komponent\Api;

use KoolKode\BPMN\Repository\RepositoryService;
use KoolKode\BPMN\Runtime\RuntimeService;
use KoolKode\BPMN\Task\TaskService;
use KoolKode\Http\Http;
use KoolKode\Http\HttpResponse;
use KoolKode\Rest\JsonEntity;
use KoolKode\Rest\Route;

class EngineResource
{
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
			'definitions' => $this->repositoryService->createProcessDefinitionQuery()->findAll()
		]);
	}
	
	/**
	 * @Route("GET /definitions/key/{key}")
	 */
	public function listDefinitionsByKey($key)
	{
		$query = $this->repositoryService->createProcessDefinitionQuery()->processDefinitionKey($key);
		
		return new JsonEntity([
			'definitions' => $query->findAll()
		]);
	}

	/**
	 * @Route("GET /definitions/{id}")
	 */
	public function showDefinition($id)
	{
		$query = $this->repositoryService->createProcessDefinitionQuery()->processDefinitionId($id);

		return new JsonEntity([
			'definition' => $query->findOne()
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
		$response->setEntity(new JsonEntity([
			'execution' => $execution,
			'variables' => $this->runtimeService->getExecutionVariables($execution->getId())
		]));

		return $response;
	}

	/**
	 * @Route("GET /executions")
	 */
	public function listExecutions()
	{
		return new JsonEntity([
			'executions' => $this->runtimeService->createExecutionQuery()->findAll()
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
			'variables' => $this->runtimeService->getExecutionVariables($execution->getId())
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
	 * @Route("POST /subscriptions/message/{name}")
	 */
	public function sendMessage($name, JsonEntity $input)
	{
		$input = $input->toArray();
		$vars = array_key_exists('variables', $input) ? $input['variables'] : [];
		$vars['registrationDate'] = (new \DateTime())->format(\DateTime::ISO8601);

		$query = $this->runtimeService->createExecutionQuery()->executionId($input['executionId']);

		$this->runtimeService->messageEventReceived($name, $query->findOne()->getId(), $vars);
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
		return new JsonEntity([
			'task' => $this->taskService->createTaskQuery()->taskId($id)->findOne()
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

		return new JsonEntity([
			'task' => (string)$task->getId(),
			'completed' => true,
			'completionDate' => (new \DateTime())->format(\DateTime::ISO8601),
			'variables' => $this->runtimeService->getExecutionVariables($task->getExecutionId())
		]);
	}
}

