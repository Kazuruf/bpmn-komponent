<?php

/*
 * This file is part of KoolKode BPMN Komponent.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\BPMN\Komponent;

use KoolKode\BPMN\Komponent\Api\EngineResource;
use KoolKode\BPMN\Komponent\Test\ProcessRule;
use KoolKode\BPMN\Repository\RepositoryService;
use KoolKode\BPMN\Runtime\RuntimeService;
use KoolKode\BPMN\Task\TaskInterface;
use KoolKode\BPMN\Task\TaskService;
use KoolKode\Context\Bind\ContainerBuilder;
use KoolKode\Context\Bind\Inject;
use KoolKode\Context\Bind\SetterInjection;
use KoolKode\Http\Entity\FileEntity;
use KoolKode\Http\Http;
use KoolKode\Http\HttpRequest;
use KoolKode\Http\Komponent\Rest\RestResource;
use KoolKode\Http\Komponent\Test\HttpRule;
use KoolKode\Http\Uri;
use KoolKode\Http\UriBuilder;
use KoolKode\Http\Komponent\Rest\JsonEntity;
use KoolKode\K2\Test\TestCase;

class MultiApiTest extends TestCase
{
	protected $processRule;
	
	protected $httpRule;
	
	/**
	 * @Inject
	 * @var RepositoryService
	 */
	protected $repositoryService;
	
	/**
	 * @Inject
	 * @var RuntimeService
	 */
	protected $runtimeService;
	
	/**
	 * @Inject
	 * @var TaskService
	 */
	protected $taskService;
	
	protected function setUpRules()
	{
		parent::setUpRules();
		
		$this->processRule = new ProcessRule();
		$this->httpRule = new HttpRule();
	}
	
	public function build(ContainerBuilder $builder)
	{
		$builder->bind(EngineResource::class)
				->marked(new RestResource('/bpmn', 'bpmn'))
				->marked(new SetterInjection());
		
		$builder->bind(ExampleTaskHandler::class)
				->marked(new TaskHandler('transmitOrder', 'main'));
	}
	
	public function provideOrders()
	{
		return [
			[27364, 'Hello world', true],
			[1337, 'N/A', false]
		];
	}
	
	/**
	 * @dataProvider provideOrders
	 */
	public function testMultipleInvocationsUsingDataProvider($id, $title, $confirmed)
	{
		if($confirmed)
		{
			$this->processRule->deployDirectory('MultiApiTest');
		}
		else
		{
			$this->processRule->deployArchive('MultiApiTest.zip');
		}
		
		$this->assertEquals(0, $this->runtimeService->createExecutionQuery()->count());
		
		$process = $this->runtimeService->startProcessInstanceByKey('main');
		
		$task = $this->taskService->createTaskQuery()->findOne();
		$this->assertTrue($task instanceof TaskInterface);
		$this->assertEquals('enterOrderData', $task->getDefinitionKey());
		$this->assertEquals(1, $this->taskService->createTaskQuery()->count());
		
		$this->taskService->complete($task->getId(), [
			'id' => $id,
			'title' => $title
		]);
		$this->assertEquals(0, $this->taskService->createTaskQuery()->count());
		
		$execution = $this->runtimeService->createExecutionQuery()->messageEventSubscriptionName('OrderRegistrationReceived')->findOne();
		$this->assertEquals($process->getId(), $execution->getId());
		
		$this->runtimeService->createMessageCorrelation('OrderRegistrationReceived')
							 ->setVariable('confirmed', $confirmed)
							 ->correlate();
		
		$task = $this->taskService->createTaskQuery()->findOne();
		$this->assertTrue($task instanceof TaskInterface);
		$this->assertEquals('verifyRegistration', $task->getDefinitionKey());
		$this->assertEquals(1, $this->taskService->createTaskQuery()->count());
		
		$this->assertEquals([
			'id' => $id,
			'title' => $title,
			'processor' => ProcessorDelegateTask::class,
			'handler' => ExampleTaskHandler::class,
			'executionVerified' => true,
			'confirmed' => $confirmed
		], $this->runtimeService->getExecutionVariables($process->getId()));
		
		$this->taskService->complete($task->getId());
		$this->assertEquals(0, $this->runtimeService->createExecutionQuery()->count());
	}
	
	public function provideArchiveSetting()
	{
		return [
			[false, __DIR__ . '/MultiApiTest/Process/MultiApiTest.bpmn'],
			[true, __DIR__ . '/MultiApiTest.zip']
		];
	}
	
	/**
	 * @dataProvider provideArchiveSetting
	 */
	public function testUsingRestApi($archive, $file)
	{
		$response = $this->httpRule->dispatch(new HttpRequest(new Uri('http://test.me/bpmn/definitions')));
		$this->assertEquals(Http::CODE_OK, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		$this->assertCount(0, json_decode($response->getContents(), true)['_embedded']['definitions']);
		
		$builder = new UriBuilder('http://test.me/bpmn/deployments/' . ($archive ? 'archive' : 'file'));
		$builder->param('name', 'Some Test Process');
		
		$request = new HttpRequest($builder->build(), Http::METHOD_POST);
		$request->setEntity(new FileEntity(new \SplFileInfo($file)));
		
		$response = $this->httpRule->dispatch($request);
		$this->assertEquals(Http::CODE_CREATED, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		
		$payload = json_decode($response->getContents(), true);
		$this->assertArrayHasKey('definitions', $payload);
		$this->assertTrue(is_array($payload['definitions']));
		
		$definition = array_pop($payload['definitions']);
		$this->assertTrue(is_array($definition));
		$this->assertArrayHasKey('id', $definition);
		
		$builder = new UriBuilder('http://test.me/bpmn/definitions/{id}');
		$builder->pathParam('id', $definition['id']);
		
		$businessKey = 'Hello World :)';
		$vars = [
			'subject' => 'world',
			'id' => 1248
		];
		
		$request = new HttpRequest($builder->build(), Http::METHOD_POST);
		$request->setEntity(new JsonEntity([
			'businessKey' => $businessKey,
			'variables' => $vars
		]));
		$response = $this->httpRule->dispatch($request);
		$this->assertEquals(Http::CODE_CREATED, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		
		$response->getHeader('Location');
		$payload = json_decode($response->getContents(), true);
		$executionId = $payload['execution']['id'];
		
		$this->assertEquals($businessKey, $payload['execution']['businessKey']);
		$this->assertEquals($vars, $payload['variables']);
		
		$request = new HttpRequest(new Uri('http://test.me/bpmn/tasks'));
		$response = $this->httpRule->dispatch($request);
		$this->assertEquals(Http::CODE_OK, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		
		$payload = json_decode($response->getContents(), true);
		$this->assertCount(1, $payload['_embedded']['tasks']);
		$task = array_pop($payload['_embedded']['tasks']);
		$this->assertEquals('enterOrderData', $task['definitionKey']);
		
		$builder = new UriBuilder('http://test.me/bpmn/tasks/{id}');
		$builder->pathParam('id', $task['id']);
		
		$request = new HttpRequest($builder->build(), Http::METHOD_POST);
		$request->setEntity(new JsonEntity([
			'productId' => 283745
		]));
		$response = $this->httpRule->dispatch($request);
		$this->assertEquals(Http::CODE_OK, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		
		$payload = json_decode($response->getContents(), true);
		
		$builder = new UriBuilder('http://test.me/bpmn/executions/{id}/message/{message}');
		$builder->pathParam('id', $executionId);
		$builder->pathParam('message', 'OrderRegistrationReceived');
		
		$request = new HttpRequest($builder->build(), Http::METHOD_POST);
		$request->setEntity(new JsonEntity([
			'confirmed' => true
		]));
		$response = $this->httpRule->dispatch($request);
		$this->assertEquals(Http::CODE_OK, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		
		$request = new HttpRequest(new Uri('http://test.me/bpmn/tasks'));
		$response = $this->httpRule->dispatch($request);
		$this->assertEquals(Http::CODE_OK, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		
		$payload = json_decode($response->getContents(), true);
		$this->assertCount(1, $payload['_embedded']['tasks']);
		$task = array_pop($payload['_embedded']['tasks']);
		$this->assertEquals('verifyRegistration', $task['definitionKey']);
		
		$builder = new UriBuilder('http://test.me/bpmn/tasks/{id}');
		$builder->pathParam('id', $task['id']);
		
		$response = $this->httpRule->dispatch(new HttpRequest($builder->build()));
		$this->assertEquals(Http::CODE_OK, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		
		$payload = json_decode($response->getContents(), true);
		$this->assertEquals([
			'subject' => 'world',
			'id' => 1248,
			'productId' => 283745,
			'confirmed' => true,
			'processor' => ProcessorDelegateTask::class,
			'handler' => ExampleTaskHandler::class,
			'executionVerified' => true
		], $payload['_embedded']['variables']);
		
		$request = new HttpRequest($builder->build(), Http::METHOD_POST);
		$request->setEntity(new JsonEntity([
			'verified' => true
		]));
		$response = $this->httpRule->dispatch($request);
		$this->assertEquals(Http::CODE_OK, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		
		$response = $this->httpRule->dispatch(new HttpRequest(new Uri('http://test.me/bpmn/executions')));
		$this->assertEquals(Http::CODE_OK, $response->getStatus());
		$this->assertTrue($response->getMediaType()->is('application/json'));
		
		$payload = json_decode($response->getContents(), true);
		$this->assertCount(0, $payload['_embedded']['executions']);
	}
}
