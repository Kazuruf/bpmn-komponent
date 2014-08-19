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
use KoolKode\Context\ContainerInterface;

/**
 * Very simple delegate task factory that just pulls task from the DI container by FQN.
 * 
 * @author Martin Schröder
 */
class ContainerDelegateTaskFactory implements DelegateTaskFactoryInterface
{
	protected $container;
	
	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}
	
	public function createDelegateTask($typeName)
	{
		return $this->container->get($typeName);
	}
}
