<?php

/*
 * This file is part of KoolKode BPMN Komponent.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\BPMN\Komponent\Test;

use KoolKode\BPMN\Delegate\DelegateTaskFactoryInterface;
use KoolKode\BPMN\Engine\ProcessEngine;
use KoolKode\BPMN\Engine\ProcessEngineInterface;
use KoolKode\Context\ContainerInterface;
use KoolKode\Database\PDO\Connection;
use KoolKode\Event\EventDispatcherInterface;
use KoolKode\Expression\ExpressionContextFactoryInterface;
use KoolKode\K2\Test\AbstractTestRule;
use KoolKode\K2\Test\TestCase;

class ProcessRule extends AbstractTestRule
{
	protected $container;
	
	protected $dsn;
	
	protected $username;
	
	protected $password;
	
	protected $conn;
	
	protected $engine;
	
	public function __construct(ContainerInterface $container, $dsn = 'sqlite::memory:', $username = NULL, $password = NULL)
	{
		$this->container = $container;
		$this->dsn = (string)$dsn;
		$this->username = ($username === NULL) ? NULL : (string)$username;
		$this->password = ($password === NULL) ? NULL : (string)$password;
	}
	
	public function before(TestCase $test)
	{
		$pdo = new \PDO($this->dsn, $this->username, $this->password);
		
		$this->conn = new Connection($pdo);
		
		$ref = new \ReflectionClass(ProcessEngine::class);
		$file = dirname($ref->getFileName()) . DIRECTORY_SEPARATOR . 'ProcessEngine.sqlite.sql';
		$chunks = explode(';', file_get_contents($file));
		
		foreach($chunks as $chunk)
		{
			$this->conn->execute($chunk);
		}
		
		$dispatcher = $this->container->get(EventDispatcherInterface::class);
		$exp = $this->container->get(ExpressionContextFactoryInterface::class);
		
		$this->engine = new ProcessEngine($this->conn, $dispatcher, $exp);
		$this->engine->setDelegateTaskFactory($this->container->get(DelegateTaskFactoryInterface::class));
		
		$this->container->bindInstance(ProcessEngineInterface::class, $this->engine);
	}
	
	public function after(TestCase $test)
	{
		static $tables = [
			'#__process_subscription',
			'#__event_subscription',
			'#__user_task',
			'#__execution_variables',
			'#__execution',
			'#__process_definition'
		];
		
		foreach($tables as $table)
		{
			$this->conn->execute("DROP TABLE IF EXISTS `$table`");
		}
	}
	
	public function getProcessEngine()
	{
		return $this->engine;
	}
	
	public function getRepositoryService()
	{
		return $this->engine->getRepositoryService();
	}
	
	public function getRuntimeService()
	{
		return $this->engine->getRuntimeService();
	}
	
	public function getTaskService()
	{
		return $this->engine->getTaskService();
	}
	
	public function deployFile($file)
	{
		if(!preg_match("'^(?:(?:[a-z]:)|(/+)|([^:]+://))'i", $file))
		{
			$file = getcwd() . DIRECTORY_SEPARATOR . $file;
		}
	
		return $this->getRepositoryService()->deployDiagram($file);
	}
}
