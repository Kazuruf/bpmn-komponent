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

use KoolKode\BPMN\Komponent\Test\ProcessRule;
use KoolKode\BPMN\Repository\RepositoryService;
use KoolKode\BPMN\Runtime\RuntimeService;
use KoolKode\BPMN\Task\TaskInterface;
use KoolKode\BPMN\Task\TaskService;
use KoolKode\Context\Bind\Inject;
use KoolKode\K2\Test\TestCase;

class ProcessEngineFactoryTest extends TestCase
{
	protected $processRule;
	
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
		$this->processRule->deployFile('ProcessEngineFactoryTest.bpmn');
		
		$this->assertEquals(0, $this->runtimeService->createExecutionQuery()->count());
		
		$process = $this->runtimeService->startProcessInstanceByKey('main');
		$this->assertEquals(1, $this->runtimeService->createExecutionQuery()->count());
		
		$task = $this->taskService->createTaskQuery()->findOne();
		$this->assertTrue($task instanceof TaskInterface);
		$this->assertEquals('enterOrderData', $task->getActivityId());
		$this->assertEquals(1, $this->runtimeService->createExecutionQuery()->count());
		$this->assertEquals(1, $this->taskService->createTaskQuery()->count());
		
		$this->taskService->complete($task->getId(), [
			'id' => $id,
			'title' => $title
		]);
		$this->assertEquals(1, $this->runtimeService->createExecutionQuery()->count());
		$this->assertEquals(0, $this->taskService->createTaskQuery()->count());
		
		$execution = $this->runtimeService->createExecutionQuery()->messageEventSubscriptionName('OrderRegistrationReceived')->findOne();
		$this->assertEquals($process->getId(), $execution->getId());
		
		$this->runtimeService->createMessageCorrelation('OrderRegistrationReceived')
							 ->setVariable('confirmed', $confirmed)
							 ->correlate();
		
		$task = $this->taskService->createTaskQuery()->findOne();
		$this->assertTrue($task instanceof TaskInterface);
		$this->assertEquals('verifyRegistration', $task->getActivityId());
		$this->assertEquals(1, $this->runtimeService->createExecutionQuery()->count());
		$this->assertEquals(1, $this->taskService->createTaskQuery()->count());
		
		$this->assertEquals([
			'id' => $id,
			'title' => $title,
			'confirmed' => $confirmed
		], $this->runtimeService->getExecutionVariables($process->getId()));
		
		$this->taskService->complete($task->getId());
		$this->assertEquals(0, $this->runtimeService->createExecutionQuery()->count());
	}
}
