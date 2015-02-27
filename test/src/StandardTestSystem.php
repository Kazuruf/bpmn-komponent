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

use KoolKode\BPMN\Komponent\Api\EngineResource;
use KoolKode\Context\Bind\ContainerBuilder;
use KoolKode\Context\Bind\SetterInjection;
use KoolKode\Context\Scope\Singleton;
use KoolKode\Http\Komponent\Rest\RestResource;
use KoolKode\K2\Komponent\KomponentLoader;
use KoolKode\K2\Test\TestSystem;
use Psr\Log\LogLevel;

class StandardTestSystem extends TestSystem
{
	public function start()
	{
		$this->migrateConnectionUp('default');
	}
	
	public function registerKomponents(KomponentLoader $komponents)
	{
		parent::registerKomponents($komponents);
		
		$komponents->registerKomponent(new \KoolKode\BPMN\Komponent\Komponent());
		$komponents->registerKomponent(new \KoolKode\Http\Komponent\Komponent());
	}
	
	public function build(ContainerBuilder $builder)
	{
		parent::build($builder);
		
		$builder->bind(EngineResource::class)
				->scoped(new Singleton())
				->marked(new SetterInjection())
				->marked(new RestResource('/api', 'api'));
	}
}
