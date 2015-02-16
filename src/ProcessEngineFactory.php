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

use KoolKode\BPMN\Delegate\DelegateTaskFactoryInterface;
use KoolKode\BPMN\Engine\ProcessEngine;
use KoolKode\BPMN\Delegate\Event\TaskExecutedEvent;
use KoolKode\BPMN\Job\Executor\JobExecutor;
use KoolKode\BPMN\Job\Scheduler\JobSchedulerInterface;
use KoolKode\Config\Configuration;
use KoolKode\Context\ContainerInterface;
use KoolKode\Context\Bind\BindingInterface;
use KoolKode\Database\ConnectionManagerInterface;
use KoolKode\Event\EventDispatcher;
use KoolKode\Expression\ExpressionContextFactoryInterface;
use KoolKode\Process\Event\AbstractProcessEvent;
use Psr\Log\LoggerInterface;

class ProcessEngineFactory
{
	protected $container;
	
	protected $factory;
	
	protected $connectionManager;
	
	protected $taskFactory;
	
	protected $scope;
	
	protected $scheduler;
	
	public function __construct(ContainerInterface $container, ExpressionContextFactoryInterface $factory)
	{
		$this->container = $container;
		$this->factory = $factory;
	}
	
	public function setConnectionManager(ConnectionManagerInterface $connectionManager)
	{
		$this->connectionManager = $connectionManager;
	}
	
	public function setTaskFactory(DelegateTaskFactoryInterface $taskFactory)
	{
		$this->taskFactory = $taskFactory;
	}
	
	public function setBusinessProcessScope(BusinessProcessScopeManager $scope)
	{
		$this->scope = $scope;
	}
	
	public function setJobScheduler(JobSchedulerInterface $scheduler = NULL)
	{
		$this->scheduler = $scheduler;
	}
	
	public function createProcessEngine(Configuration $config, LoggerInterface $logger = NULL)
	{
		$conn = $this->connectionManager->getConnection($config->getString('connection', 'default'));
		$transactional = $config->getBoolean('transactional', true);
		
		$dispatcher = $this->container->get(EventDispatcher::class);
		
		$engine = new ProcessEngine($conn, $dispatcher, $this->factory, $transactional);
		$engine->setDelegateTaskFactory($this->taskFactory);
		$engine->registerExecutionInterceptor(new ScopeExecutionInterceptor($this->scope));
		$engine->setLogger($logger);
		
		// Load job executor when a scheduler is available and register all job handlers using DI marker.
		if($this->scheduler !== NULL)
		{
			$executor = new JobExecutor($engine, $this->scheduler);
			
			$this->container->eachMarked(function(JobHandler $handler, BindingInterface $binding) use($executor) {
				$executor->registerJobHandler($this->container->getBound($binding));
			});
			
			$engine->setJobExecutor($executor);
		}
		
		$dispatcher->connect(function(AbstractProcessEvent $event) {
			$this->scope->enterContext($event->execution);
		});
		
		$dispatcher->connect(function(TaskExecutedEvent $event) {
			
			$query = $event->engine->getRuntimeService()->createExecutionQuery();
			$query->executionId($event->execution->getExecutionId());
			
			$definition = $query->findOne()->getProcessDefinition();
			$processKey = $definition->getKey();
			$taskKey = $event->execution->getActivityId();
			
			$this->container->eachMarked(function(TaskHandler $handler, BindingInterface $binding) use($event, $taskKey, $processKey) {
				if($taskKey == $handler->taskKey) {
					if($handler->processKey === NULL || $handler->processKey == $processKey) {
						$task = $this->container->getBound($binding);
						if(!$task instanceof TaskHandlerInterface) {
							throw new \RuntimeException('Invalid task handler implementation: ' . get_class($task));
						}
						$task->executeTask($event->execution);
					}
				}
			});
		});
		
		return $engine;
	}
}
