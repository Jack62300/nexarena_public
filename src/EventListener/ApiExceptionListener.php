<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Intercepte les exceptions d'authentification/autorisation sur les routes /api/
 * et retourne une réponse JSON au lieu de la redirection vers la page de login.
 * Priority 100 → s'exécute avant le SecurityExceptionListener (priority 1).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 100)]
class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        // S'applique uniquement aux routes /api/ ou aux requêtes qui attendent du JSON
        if (!str_starts_with($path, '/api/') && !$this->isJsonRequest($request)) {
            return;
        }

        $e = $event->getThrowable();

        if ($e instanceof AccessDeniedException) {
            $event->setResponse(new JsonResponse(
                ['error' => 'forbidden', 'message' => 'Accès non autorisé. Authentification requise.'],
                Response::HTTP_FORBIDDEN,
            ));
            $event->stopPropagation();
            return;
        }

        if ($e instanceof AuthenticationException) {
            $event->setResponse(new JsonResponse(
                ['error' => 'unauthenticated', 'message' => 'Authentification requise pour accéder à cette ressource.'],
                Response::HTTP_UNAUTHORIZED,
            ));
            $event->stopPropagation();
        }
    }

    private function isJsonRequest(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        $accept = $request->headers->get('Accept', '');
        return str_contains($accept, 'application/json') || str_contains($accept, 'application/ld+json');
    }
}
