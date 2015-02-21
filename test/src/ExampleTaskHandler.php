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

use KoolKode\BPMN\Delegate\DelegateExecutionInterface;

class ExampleTaskHandler implements TaskHandlerInterface
{
	protected $execution;
	
	public function __construct(DelegateExecutionInterface $execution)
	{
		$this->execution = $execution;
	}
	
	public function executeTask(DelegateExecutionInterface $execution)
	{
		$verified = $this->execution->getProcessInstanceId() == $execution->getProcessInstanceId();
		
		$execution->setVariable('handler', get_class($this));
		$execution->setVariable('executionVerified', $verified);
	}
}
