<?php

namespace KoolKode\BPMN\Komponent;

use KoolKode\BPMN\Delegate\DelegateExecutionInterface;
use KoolKode\BPMN\Delegate\DelegateTaskInterface;

class TestDelegateTask implements DelegateTaskInterface
{
	public function execute(DelegateExecutionInterface $execution)
	{
		$execution->setVariable('processor', get_class($this));
	}
}
