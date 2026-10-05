<?php

namespace App\Service;

use App\Entity\ErrorLog;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Durable error history — separate from ApiExceptionSubscriber's job of
 * turning an exception into an HTTP response. That subscriber runs on
 * every request regardless of whether this service exists; this service
 * only adds a persistent record of what happened, so "what was the last
 * error" is an answerable question after the fact, not just something
 * visible in the one response that already came and went.
 *
 * record() never lets a logging failure become a second, masking error —
 * every call is wrapped so a problem writing to error_log itself (e.g. a
 * DB connection issue) is swallowed, not re-thrown into the real request.
 */
class ErrorLogService
{
    // Sanity caps on what gets stored — this is a diagnostic record, not
    // an unbounded text dump.
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_TRACE_LENGTH   = 4000;
    private const MAX_URL_LENGTH     = 255;

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function recordException(\Throwable $exception, ?Request $request = null): void
    {
        try {
            $log = new ErrorLog();
            $log->setSource('backend');
            $log->setExceptionClass(get_class($exception));
            $log->setMessage($this->truncate($exception->getMessage(), self::MAX_MESSAGE_LENGTH));
            $log->setFile($exception->getFile());
            $log->setLine($exception->getLine());
            $log->setTrace($this->truncate($exception->getTraceAsString(), self::MAX_TRACE_LENGTH));

            if ($request) {
                $log->setRequestUrl($this->truncate($request->getPathInfo(), self::MAX_URL_LENGTH));
                $log->setRequestMethod($request->getMethod());
                $log->setIpAddress($request->getClientIp());
            }

            $user = $this->security->getUser();
            if ($user instanceof Utilisateur) {
                $log->setUser($user);
            }

            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable) {
            // Deliberately swallowed -- see class docstring. Logging
            // itself failing must never surface as a second exception.
        }
    }

    /**
     * For a frontend-reported error (see ErrorLogController::reportClientError)
     * — no PHP exception object exists, just whatever the browser sent.
     */
    public function recordClientError(
        string $message,
        ?string $stack,
        ?string $url,
        ?Request $request = null,
    ): void {
        try {
            $log = new ErrorLog();
            $log->setSource('frontend');
            $log->setMessage($this->truncate($message, self::MAX_MESSAGE_LENGTH));
            $log->setTrace($stack !== null ? $this->truncate($stack, self::MAX_TRACE_LENGTH) : null);
            $log->setRequestUrl($url !== null ? $this->truncate($url, self::MAX_URL_LENGTH) : null);

            if ($request) {
                $log->setIpAddress($request->getClientIp());
            }

            $user = $this->security->getUser();
            if ($user instanceof Utilisateur) {
                $log->setUser($user);
            }

            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable) {
            // Same guarantee as recordException().
        }
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) return null;
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) . '…' : $value;
    }
}
