<?php

declare (strict_types = 1);

namespace App;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\Emitter\SapiEmitter;
use Zend\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Zend\HttpHandlerRunner\Emitter\EmitterStack;

/**
 * @see https://docs.zendframework.com/zend-httphandlerrunner/emitters/
 */
class EmitterFactory
{
    public function __invoke(ContainerInterface $container): EmitterInterface
    {
        $sapiStreamEmitter = new SapiStreamEmitter();
        $conditionalEmitter = new class ($sapiStreamEmitter) implements EmitterInterface
        {
            private $emitter;

            public function __construct(EmitterInterface $emitter)
            {
                $this->emitter = $emitter;
            }

            public function emit(ResponseInterface $response): bool
            {
                if (!$response->hasHeader('Content-Disposition') && !$response->hasHeader('Content-Range')) {
                    return false;
                }
                return $this->emitter->emit($response);
            }
        };

        $stack = new EmitterStack();
        $stack->push(new SapiEmitter());
        $stack->push($conditionalEmitter);

        return $stack;
    }
}
