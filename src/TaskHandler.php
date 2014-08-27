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

use KoolKode\Context\Bind\Marker;

final class TaskHandler extends Marker
{
	public $taskKey;
	
	public $processKey;
	
	public function __construct($taskKey, $processKey = NULL)
	{
		$this->taskKey = (string)$taskKey;
		$this->processKey = ($processKey === NULL) ? NULL : (string)$processKey;
	}
}
