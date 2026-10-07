<?php

namespace App\EventSubscriber;

use App\Fleet\Exception\InvalidReservationTransitionException;
use App\Fleet\Exception\InvalidTransitionException;
use App\Fleet\Exception\LifecycleViolationException;
use App\Service\ErrorLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts FleetLifecycleManager exceptions to structured JSON responses.
 * Priority 20 — runs before ApiExceptionSubscriber (priority 10).
 *
 * LifecycleViolationException → 422  (guard failed, user-facing message)
 * InvalidTransitionException  → 500  (programmer error, recorded to the
 *                                error log — see App\Service\ErrorLogService)
 */
class LifecycleExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ErrorLogService $errorLog) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 20]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof LifecycleViolationException) {
            $event->setResponse(new JsonResponse(
                $exception->toApiError(),
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            ));
            $event->stopPropagation();
            return;
        }

        if ($exception instanceof InvalidReservationTransitionException) {
            $event->setResponse(new JsonResponse(
                $exception->toApiError(),
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            ));
            $event->stopPropagation();
            return;
        }

        if ($exception instanceof InvalidTransitionException) {
            $this->errorLog->recordException($exception, $event->getRequest());

            $event->setResponse(new JsonResponse([
                'error'      => 'invalid_transition',
                'message'    => 'A lifecycle configuration error occurred. The fleet administrator has been notified.',
                'voiture_id' => $exception->getVoitureId(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR));
            $event->stopPropagation();
        }
    }
}
