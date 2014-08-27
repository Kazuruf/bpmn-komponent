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

use KoolKode\BPMN\Delegate\DelegateExecution;
use KoolKode\BPMN\Delegate\DelegateExecutionInterface;
use KoolKode\BPMN\Engine\VirtualExecution;
use KoolKode\Context\Scope\AbstractScopeManager;
use KoolKode\Context\Scope\Scope;
use KoolKode\Context\Scope\ScopedContainerInterface;

/**
 * Manages contextual instances related to a business process.
 * 
 * @author Martin Schröder
 */
class BusinessProcessScopeManager extends AbstractScopeManager
{
	/**
	 * {@inheritdoc}
	 */
	public function getScope()
	{
		return BusinessProcessScoped::class;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getProxyTypeNames()
	{
		return [DelegateExecutionInterface::class];
	}

	/**
	 * Get the current context execution (or NULL when none is active).
	 * 
	 * @return VirtualExecution
	 */
	public function getContextExecution()
	{
		return $this->context;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function correlate(ScopedContainerInterface $container)
	{
		parent::correlate($container);
		
		$this->bindFactoryProxy(DelegateExecutionInterface::class, function() {
			return new DelegateExecution($this->context);
		});
	}
	
	/**
	 * Associate the business process scope with the given execution.
	 * 
	 * @param VirtualExecution $execution
	 * @return object or NULL when no execution context was active.
	 */
	public function enterContext(VirtualExecution $execution = NULL)
	{
		return parent::bindContext($execution);
	}
	
	/**
	 * Clear current execution context, will not(!) destroy any contextual instances.
	 * 
	 * It is your responsibility to clear() the scope manager when all execution-related work is done.
	 */
	public function leaveContext()
	{
		return parent::bindContext(NULL);
	}
	
	/**
	 * Destroy all contextual instances within the scope of the given execution.
	 * 
	 * @param VirtualExecution $execution
	 */
	public function destroyContext(VirtualExecution $execution)
	{
		return parent::unbindContext($execution);
	}
}
