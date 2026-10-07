<?php

namespace App\Tests\Trait;

use App\Entity\Bureau;
use App\Entity\Utilisateur;
use App\Trait\BureauAwareTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Minimal harness that uses the trait exactly like a real controller does,
 * but with no Symfony container/security machinery involved -- getUser() is
 * just a settable property, so these tests exercise the trait's own
 * branching logic in total isolation, with no database required.
 */
final class BureauAwareTraitHarness
{
    use BureauAwareTrait;

    public function __construct(private ?Utilisateur $user, RequestStack $requestStack)
    {
        $this->setBureauAwareRequestStack($requestStack);
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function createNotFoundException(string $message = ''): NotFoundHttpException
    {
        return new NotFoundHttpException($message);
    }

    public function resolve(): ?int
    {
        return $this->getEffectiveBureauId();
    }
}

final class BureauAwareTraitTest extends TestCase
{
    private static function bureau(int $id): Bureau
    {
        $bureau = new Bureau();
        $ref = new \ReflectionProperty(Bureau::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($bureau, $id);

        return $bureau;
    }

    private static function requestStack(array $query = []): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(new Request($query));

        return $stack;
    }

    public function testNoAuthenticatedUserReturnsNull(): void
    {
        $harness = new BureauAwareTraitHarness(null, self::requestStack());

        $this->assertNull($harness->resolve());
    }

    public function testUserWithOwnBureauIsLockedToIt(): void
    {
        $user = new Utilisateur();
        $user->setRoles(['ROLE_STAFF']);
        $user->setBureau(self::bureau(7));

        $harness = new BureauAwareTraitHarness($user, self::requestStack());

        $this->assertSame(7, $harness->resolve());
    }

    public function testUserWithOwnBureauIsLockedToItEvenIfAdmin(): void
    {
        // Regression: an admin account that ALSO has a personal bureau assigned
        // must still be locked to it -- bureau assignment takes priority over
        // "true admin, unrestricted". This is the exact shape of the real admin
        // test account used throughout today's manual testing (bureau #1).
        $user = new Utilisateur();
        $user->setRoles(['ROLE_ADMIN']);
        $user->setBureau(self::bureau(1));

        $harness = new BureauAwareTraitHarness($user, self::requestStack());

        $this->assertSame(1, $harness->resolve());
    }

    public function testTrueAdminWithNoBureauIsUnrestrictedByDefault(): void
    {
        $user = new Utilisateur();
        $user->setRoles(['ROLE_ADMIN']);

        $harness = new BureauAwareTraitHarness($user, self::requestStack());

        $this->assertNull($harness->resolve());
    }

    public function testTrueAdminCanSelfNarrowViaQueryParam(): void
    {
        $user = new Utilisateur();
        $user->setRoles(['ROLE_ADMIN']);

        $harness = new BureauAwareTraitHarness($user, self::requestStack(['bureauId' => '5']));

        $this->assertSame(5, $harness->resolve());
    }

    public function testNonAdminWithNoBureauGetsSentinelNotUnrestricted(): void
    {
        // This is the god-mode bug found and fixed in production today: a
        // non-admin with no bureau must NEVER resolve to null (unrestricted).
        // It must resolve to a value that matches no real bureau, so every
        // "= :bureauId" check across the app correctly sees "nothing", not
        // "everything".
        $user = new Utilisateur();
        $user->setRoles(['ROLE_STAFF']);

        $harness = new BureauAwareTraitHarness($user, self::requestStack());
        $result = $harness->resolve();

        $this->assertNotNull($result, 'A bureau-less non-admin must never resolve to null (unrestricted).');
        $this->assertSame(0, $result);
    }

    public function testNonAdminQueryParamIsIgnored(): void
    {
        // Only ROLE_ADMIN may self-select a bureau via ?bureauId=. A non-admin
        // passing the same param must be ignored -- otherwise one staff
        // account could read another bureau's data just by changing the URL.
        $user = new Utilisateur();
        $user->setRoles(['ROLE_STAFF']);

        $harness = new BureauAwareTraitHarness($user, self::requestStack(['bureauId' => '99']));

        $this->assertSame(0, $harness->resolve());
    }

    public function testManagerRoleAloneWithNoBureauGetsSentinel(): void
    {
        // Confirms the old Bureau.manager fallback is truly gone: being named
        // as a bureau's manager used to be enough on its own to resolve that
        // bureau even with no personal bureau set (this is exactly what let a
        // real account keep its access after its bureau field was cleared,
        // found during manual testing today). ROLE_MANAGER with no bureau is
        // now treated exactly like any other misconfigured non-admin account.
        $user = new Utilisateur();
        $user->setRoles(['ROLE_MANAGER']);

        $harness = new BureauAwareTraitHarness($user, self::requestStack());

        $this->assertSame(0, $harness->resolve());
    }
}
