<?php declare(strict_types=1);

namespace Monarc\BackOffice\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Laminas\Diactoros\ResponseFactory;
use Monarc\BackOffice\Service\ClientApiTokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Routes appelées par les FO : l'entité est identifiée par son jeton (Authorization: Bearer).
 */
class EntityApiTokenMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ClientApiTokenService $clientApiTokenService,
        private ResponseFactory $responseFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $client = null;
        if (preg_match('/^Bearer ([0-9a-f]{64})$/', $request->getHeaderLine('Authorization'), $matches)) {
            $client = $this->clientApiTokenService->findClientByToken($matches[1]);
        }
        if ($client === null) {
            return $this->responseFactory->createResponse(StatusCodeInterface::STATUS_UNAUTHORIZED)
                ->withHeader('WWW-Authenticate', 'Bearer');
        }

        return $handler->handle($request->withAttribute('client', $client));
    }
}
