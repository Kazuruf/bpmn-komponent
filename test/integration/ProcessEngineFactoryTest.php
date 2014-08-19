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

use KoolKode\BPMN\Engine\ProcessEngineInterface;
use KoolKode\BPMN\Repository\RepositoryService;
use KoolKode\BPMN\Runtime\RuntimeService;
use KoolKode\BPMN\Task\TaskInterface;
use KoolKode\BPMN\Task\TaskService;
use KoolKode\Database\ConnectionManagerInterface;
use KoolKode\K2\Komponent\KomponentLoader;
use KoolKode\K2\Test\TestCase;
use KoolKode\K2\Test\TestConfigLoader;

class ProcessEngineFactoryTest extends TestCase
{
	protected $connectionManager;
	
	protected $engine;
	
	protected $repositoryService;
	
	protected $runtimeService;
	
	protected $taskService;
	
	public function registerKomponents(KomponentLoader $komponents)
	{
		$komponents->registerKomponent(new \KoolKode\BPMN\Komponent\Komponent());
	}
	
	public function loadConfigurationSources(TestConfigLoader $loader)
	{
		$loader->addFile(__DIR__ . '/ProcessEngineFactoryTest.yml');
	}
	
	public function injectConnectionManager(ConnectionManagerInterface $connectionManager)
	{
		$this->connectionManager = $connectionManager;
	}
	
	public function injectProcessEngine(ProcessEngineInterface $engine)
	{
		$this->engine = $engine;
	}
	
	public function injectRepositoryService(RepositoryService $repositoryService)
	{
		$this->repositoryService = $repositoryService;
	}
	
	public function injectRuntimeService(RuntimeService $runtimeService)
	{
		$this->runtimeService = $runtimeService;
	}
	
	public function injectTaskService(TaskService $taskService)
	{
		$this->taskService = $taskService;
	}
	
	protected function setUp()
	{
		parent::setUp();
		
		$ref = new \ReflectionClass(ProcessEngineInterface::class);
		$file = dirname($ref->getFileName()) . DIRECTORY_SEPARATOR . 'ProcessEngine.sqlite.sql';
		$chunks = explode(';', file_get_contents($file));
		$conn = $this->connectionManager->getConnection('default');
		
		foreach($chunks as $chunk)
		{
			$conn->execute($chunk);
		}
	}
	
	public function testSimpleIntegration()
	{
		$this->deployFile('ProcessEngineFactoryTest.bpmn');
		
		$this->assertEquals(0, $this->runtimeService->createExecutionQuery()->count());
		
		$process = $this->runtimeService->startProcessInstanceByKey('main');
		$this->assertEquals(1, $this->runtimeService->createExecutionQuery()->count());
		
		$task = $this->taskService->createTaskQuery()->findOne();
		$this->assertTrue($task instanceof TaskInterface);
		$this->assertEquals('enterOrderData', $task->getActivityId());
		$this->assertEquals(1, $this->runtimeService->createExecutionQuery()->count());
		$this->assertEquals(1, $this->taskService->createTaskQuery()->count());
		
		$this->taskService->complete($task->getId(), [
			'id' => 2355,
			'title' => 'New product order'
		]);
		$this->assertEquals(1, $this->runtimeService->createExecutionQuery()->count());
		$this->assertEquals(0, $this->taskService->createTaskQuery()->count());
		
		$execution = $this->runtimeService->createExecutionQuery()->messageEventSubscriptionName('OrderRegistrationReceived')->findOne();
		$this->assertEquals($process->getId(), $execution->getId());
		
		$this->runtimeService->createMessageCorrelation('OrderRegistrationReceived')
							 ->setVariable('confirmed', time())
							 ->correlate();
		
		$task = $this->taskService->createTaskQuery()->findOne();
		$this->assertTrue($task instanceof TaskInterface);
		$this->assertEquals('verifyRegistration', $task->getActivityId());
		$this->assertEquals(1, $this->runtimeService->createExecutionQuery()->count());
		$this->assertEquals(1, $this->taskService->createTaskQuery()->count());
		
		$this->taskService->complete($task->getId());
		$this->assertEquals(0, $this->runtimeService->createExecutionQuery()->count());
	}
	
	protected function deployFile($file)
	{
		if(!preg_match("'^(?:(?:[a-z]:)|(/+)|([^:]+://))'i", $file))
		{
			$file = dirname((new \ReflectionClass(get_class($this)))->getFileName()) . DIRECTORY_SEPARATOR . $file;
		}
	
		return $this->repositoryService->deployDiagram($file);
	}
}
