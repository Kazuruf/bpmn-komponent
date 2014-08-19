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
use KoolKode\Config\Configuration;
use KoolKode\Database\ConnectionManagerInterface;
use KoolKode\Event\EventDispatcherInterface;
use KoolKode\Expression\ExpressionContextFactoryInterface;
use Psr\Log\LoggerInterface;

class ProcessEngineFactory
{
	protected $dispatcher;
	
	protected $factory;
	
	protected $connectionManager;
	
	protected $taskFactory;
	
	public function __construct(EventDispatcherInterface $dispatcher, ExpressionContextFactoryInterface $factory)
	{
		$this->dispatcher = $dispatcher;
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
	
	public function createProcessEngine(Configuration $config, LoggerInterface $logger = NULL)
	{
		$conn = $this->connectionManager->getConnection($config->getString('connection', 'default'));
		$transactional = $config->getBoolean('transactional', true);
		
		$engine = new ProcessEngine($conn, $this->dispatcher, $this->factory, $transactional);
		$engine->setDelegateTaskFactory($this->taskFactory);
		$engine->setLogger($logger);
		
		return $engine;
	}
}
