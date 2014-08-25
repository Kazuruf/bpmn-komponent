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

use KoolKode\BPMN\Engine\ProcessEngineInterface;
use KoolKode\BPMN\Komponent\Komponent;
use KoolKode\Database\ConnectionInterface;
use KoolKode\Database\ConnectionManagerInterface;
use KoolKode\K2\Komponent\KomponentLoader;
use KoolKode\K2\Test\AbstractTestRule;
use KoolKode\K2\Test\TestCase;
use KoolKode\K2\Test\TestConfigLoader;

class ProcessRule extends AbstractTestRule
{	
	/**
	 * @var ConnectionInterface
	 */
	protected $conn;
	
	/**
	 * @var ProcessEngineInterface
	 */
	protected $engine;
	
	public function registerKomponents(KomponentLoader $komponents)
	{
		$komponents->registerKomponent(new Komponent());
	}
	
	public function loadConfigurationSources(TestConfigLoader $loader)
	{
		$loader->addFile(__DIR__ . DIRECTORY_SEPARATOR . 'ProcessRule.yml');
	}
	
	public function bootConnection(ConnectionManagerInterface $manager)
	{
		$this->conn = $manager->getConnection('test-bpmn');
	}
	
	public function before(TestCase $test)
	{
		$ref = new \ReflectionClass(ProcessEngineInterface::class);
		$file = sprintf('%s/ProcessEngine.%s.sql', dirname($ref->getFileName()), $this->conn->getDriverName());
		
		foreach((array)explode(';', file_get_contents($file)) as $chunk)
		{
			if('' == trim($chunk))
			{
				continue;
			}
			
			$this->conn->execute($chunk);
		}
		
		$this->engine = $this->container->get(ProcessEngineInterface::class);
	}
	
	public function after(TestCase $test)
	{
		static $tables = [
			'#__process_subscription',
			'#__event_subscription',
			'#__user_task',
			'#__execution_variables',
			'#__execution',
			'#__process_definition',
			'#__resource',
			'#__deployment'
		];
		
		if($this->conn)
		{
			foreach($tables as $table)
			{
				$this->conn->execute("DROP TABLE IF EXISTS `$table`");
			}
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
	
	public function deployFile($file, $name = NULL)
	{
		if(!preg_match("'^(?:(?:[a-z]:)|(/+)|([^:]+://))'i", $file))
		{
			$file = getcwd() . DIRECTORY_SEPARATOR . $file;
		}
	
		return $this->getRepositoryService()->deployProcess(new \SplFileInfo($file), $name);
	}
	
	public function deployArchive($file, $name = NULL, array $extensions = [])
	{
		if(!preg_match("'^(?:(?:[a-z]:)|(/+)|([^:]+://))'i", $file))
		{
			$file = getcwd() . DIRECTORY_SEPARATOR . $file;
		}
		
		if($name === NULL)
		{
			$name = basename($file);
		}
		
		$builder = $this->getRepositoryService()->createDeployment($name);
		$builder->addExtensions($extensions);
		$builder->addArchive($file);
	
		return $this->getRepositoryService()->deploy($builder);
	}
}
