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

use KoolKode\BPMN\Engine\ExecutionInterceptorChain;
use KoolKode\BPMN\Engine\ExecutionInterceptorInterface;

class ScopeExecutionInterceptor implements ExecutionInterceptorInterface
{
	protected $scope;
	
	public function __construct(BusinessProcessScopeManager $scope)
	{
		$this->scope = $scope;
	}
	
	public function getPriority()
	{
		return 1000;
	}
	
	public function interceptExecution(ExecutionInterceptorChain $chain, $depth)
	{
		$previous = $this->scope->getContextExecution();
		
		try
		{
			return $chain->performExecution();
		}
		finally
		{
			$this->scope->enterContext($previous);
			
			if($depth == 0)
			{
				$this->scope->clear();
			}
		}
	}
}
