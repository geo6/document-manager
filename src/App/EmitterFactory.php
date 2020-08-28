<?php

declare(strict_types=1);

namespace App;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @see https://docs.zendframework.com/zend-httphandlerrunner/emitters/
 */
class EmitterFactory
{
    public function __invoke(ContainerInterface $container): EmitterInterface
    {
        $sapiStreamEmitter = new SapiStreamEmitter();
        $conditionalEmitter = new class($sapiStreamEmitter) implements EmitterInterface {
            /** @var EmitterInterface */
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
