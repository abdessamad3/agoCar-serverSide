<?php

namespace App\EventSubscriber;

use App\Service\ErrorLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SecurityAccessDeniedException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

/**
 * Catches all unhandled exceptions on /api/* routes and returns a
 * structured JSON error response so the frontend always receives
 * { "success": false, "message": "..." } instead of an HTML error page.
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private string $environment,
        private ErrorLogService $errorLog,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Priority 10 → runs before Symfony's default exception handler
        return [KernelEvents::EXCEPTION => ['onException', 10]];
    }

    public function onException(ExceptionEvent $event): void
    {
        // Only intercept API routes
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $status    = 500;
        $message   = 'An unexpected error occurred.';

        if ($exception instanceof InsufficientAuthenticationException) {
            $status  = 401;
            $message = $this->defaultMessage(401);
        } elseif ($exception instanceof SecurityAccessDeniedException) {
            $status  = 403;
            $message = $this->defaultMessage(403);
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status  = $exception->getStatusCode();
            $message = $exception->getMessage() ?: $this->defaultMessage($status);
        }

        $body = [
            'success' => false,
            'message' => self::utf8($message),
        ];

        // Only unexpected (500) errors are recorded -- a routine 404/422/403
        // is normal application flow, not something worth a durable history
        // entry for. See App\Service\ErrorLogService.
        if ($status === 500) {
            $this->errorLog->recordException($exception, $event->getRequest());
        }

        // TEMP DEBUG — remove after diagnosing prod 500s
        if ($status === 500) {
            $body['_exc'] = get_class($exception);
            $body['_msg'] = self::utf8($exception->getMessage());
        }

        // Expose stack trace only in dev — never in prod
        if ($this->environment === 'dev') {
            $body['debug'] = [
                'exception' => get_class($exception),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'trace'     => array_map(
                    fn(string $line) => self::utf8($line),
                    array_slice(explode("\n", $exception->getTraceAsString()), 0, 10)
                ),
            ];
        }

        $event->setResponse(new JsonResponse($body, $status));
    }

    /**
     * OS-level exception messages (e.g. Windows DNS/socket errors) can come
     * back in the system's local codepage rather than UTF-8, which would
     * otherwise make json_encode() fail for the whole error response and
     * mask the real error behind a confusing "Malformed UTF-8" message.
     */
    private static function utf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $fixed = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');

        return $fixed !== false ? $fixed : preg_replace('/[\x80-\xFF]/', '', $value);
    }

    private function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized — please log in again',
            403 => 'Forbidden — you do not have permission',
            404 => 'Resource not found',
            405 => 'Method not allowed',
            409 => 'Conflict',
            413 => 'Payload too large',
            415 => 'Unsupported media type',
            422 => 'Unprocessable entity',
            429 => 'Too many requests',
            500 => 'Internal server error',
            default => 'Error',
        };
    }
}
