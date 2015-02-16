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

/**
 * DI marker being used to auto-register BPMN job handlers with the job executor.
 * 
 * @author Martin Schröder
 */
final class JobHandler extends Marker { }
