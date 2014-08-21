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

use KoolKode\BPMN\Engine\ProcessEngine;
use KoolKode\Console\AbstractCommand;
use KoolKode\Console\Application;
use KoolKode\Console\InputInterface;
use KoolKode\Console\OutputInterface;
use KoolKode\Database\ConnectionManagerInterface;

class CreateSchemaCommand extends AbstractCommand
{
	protected $manager;
	
	public function __construct(ConnectionManagerInterface $manager)
	{
		$this->manager = $manager;
	}
	
	public function getDescription()
	{
		return 'Generates schema objects needed by the BPMN engine';
	}
	
	public function process(Application $app, InputInterface $input, OutputInterface $output)
	{
		$output->writeLine('Choose database connection: ');
		$name = $input->readLine();
		
		$conn = $this->manager->getConnection($name);
		$file = (new \ReflectionClass(ProcessEngine::class))->getFileName();
		$file = sprintf('%s/ProcessEngine.%s.sql', dirname($file), $conn->getDriverName());
		
		foreach(explode(';', file_get_contents($file)) as $chunk)
		{
			$conn->execute($chunk);
			
			$output->writeLine('SQL >> ' . trim(preg_replace("'\s+'", ' ', $chunk)));
		}
	}
}
