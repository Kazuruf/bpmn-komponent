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
use KoolKode\BPMN\Engine\ProcessEngineInterface;
use KoolKode\BPMN\Repository\RepositoryService;
use KoolKode\BPMN\Runtime\RuntimeService;
use KoolKode\BPMN\Task\TaskService;
use KoolKode\Context\Bind\ContainerBuilder;
use KoolKode\Context\Bind\SetterInjection;
use KoolKode\Context\Scope\ApplicationScoped;
use KoolKode\Context\Scope\ScopeLoader;
use KoolKode\Context\Scope\ScopeProviderInterface;
use KoolKode\Context\Scope\Singleton;
use KoolKode\K2\Komponent\AbstractKomponent;
use KoolKode\BPMN\Delegate\DelegateExecutionInterface;

final class Komponent extends AbstractKomponent implements ScopeProviderInterface
{
	public function getKey()
	{
		return 'koolkode/bpmn-komponent';
	}
	
	public function getHomepage()
	{
		return 'https://github.com/koolkode/bpmn-komponent';
	}
	
	public function loadScopes(ScopeLoader $loader)
	{
		$loader->registerScope(new BusinessProcessScopeManager());
	}
	
	public function build(ContainerBuilder $builder)
	{
		$builder->bind(DelegateTaskFactoryInterface::class)
				->scoped(new Singleton())
				->to(ContainerDelegateTaskFactory::class);
		
		$builder->bind(ProcessEngineFactory::class)
				->scoped(new Singleton())
				->marked(new SetterInjection());
		
		$builder->bind(ProcessEngineInterface::class)
				->toAlias(ProcessEngine::class);
		
		$builder->bind(ProcessEngine::class)
				->scoped(new ApplicationScoped())
				->to(ProcessEngineFactory::class, 'createProcessEngine');
		
		$builder->bind(RepositoryService::class)
				->scoped(new ApplicationScoped())
				->to(ProcessEngineInterface::class, 'getRepositoryService');
		
		$builder->bind(RuntimeService::class)
				->scoped(new ApplicationScoped())
				->to(ProcessEngineInterface::class, 'getRuntimeService');
		
		$builder->bind(TaskService::class)
				->scoped(new ApplicationScoped())
				->to(ProcessEngineInterface::class, 'getTaskService');
	}
}
