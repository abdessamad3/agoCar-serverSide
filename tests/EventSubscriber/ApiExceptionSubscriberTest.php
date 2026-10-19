<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiExceptionSubscriber;
use App\Service\ErrorLogService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Phase 12.2 Stage 1: confirms that unhandled 500s on /api/* never leak
 * exception class, message, file path, or stack trace in a non-dev
 * environment. Before this fix, '_exc'/'_msg' were added unconditionally
 * (a "TEMP DEBUG — remove after diagnosing prod 500s" block that was never
 * removed), independent of the (correct) dev-only gate on the 'debug' key.
 */
final class ApiExceptionSubscriberTest extends TestCase
{
    private function makeEvent(string $environment): array
    {
        $errorLog = $this->createMock(ErrorLogService::class);
        $errorLog->expects($this->once())->method('recordException');

        $subscriber = new ApiExceptionSubscriber($environment, $errorLog);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/voiture', 'GET');

        $event = new ExceptionEvent(
            $kernel,
            $request,
            KernelInterface::MAIN_REQUEST,
            new \RuntimeException('leaked secret detail: /var/www/secret/path.php')
        );

        $subscriber->onException($event);

        return json_decode($event->getResponse()->getContent(), true);
    }

    public function testProdResponseLeaksNoExceptionDetails(): void
    {
        $body = $this->makeEvent('prod');

        $this->assertArrayNotHasKey('debug', $body);
        $this->assertArrayNotHasKey('_exc', $body);
        $this->assertArrayNotHasKey('_msg', $body);
        $this->assertSame('An unexpected error occurred.', $body['message']);
        // The raw exception message (which could contain file paths, SQL
        // fragments, etc.) must not appear anywhere in the response body.
        $this->assertStringNotContainsString('secret', json_encode($body));
    }

    public function testTestEnvResponseLeaksNoExceptionDetails(): void
    {
        // Same assertion for 'test' -- the gate is environment==='dev', not
        // a blocklist of just 'prod', so any non-dev environment is covered.
        $body = $this->makeEvent('test');

        $this->assertArrayNotHasKey('debug', $body);
        $this->assertArrayNotHasKey('_exc', $body);
        $this->assertArrayNotHasKey('_msg', $body);
    }

    public function testDevResponseStillIncludesDiagnosticDetail(): void
    {
        // The dev-only debug block is intentional and must keep working for
        // local debugging -- this fix only closes the prod/test leak.
        $body = $this->makeEvent('dev');

        $this->assertArrayHasKey('debug', $body);
        $this->assertSame('RuntimeException', $body['debug']['exception']);
        $this->assertStringContainsString('leaked secret detail', $body['debug']['message']);
    }
}
