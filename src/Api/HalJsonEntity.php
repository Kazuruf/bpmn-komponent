<?php

/*
 * This file is part of KoolKode BPMN Komponent.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\BPMN\Komponent\Api;

use KoolKode\Http\Header\ContentTypeHeader;
use KoolKode\Http\HttpMessage;
use KoolKode\Rest\JsonEntity;

class HalJsonEntity extends JsonEntity
{
	public function prepare(HttpMessage $message)
	{
		$header = new ContentTypeHeader('application/hal+json');
		$header->setCharset('utf-8');
		
		$message->setHeader($header);
	}
}
