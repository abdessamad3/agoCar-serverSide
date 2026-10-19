<?php

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Phase 12.9 QA pass: PasswordController had zero test coverage anywhere in
 * this suite before now, despite enforcing the only password-strength rule
 * in the application (min 8 chars, uppercase, number, special character).
 */
final class PasswordControllerTest extends ApiTestCase
{
    public function testChangeRejectsShortPassword(): void
    {
        $user = $this->makeUser('pwtest1@test.local', ['ROLE_STAFF'], null);

        $this->httpClient->loginUser($user);
        $this->httpClient->request(
            'POST',
            '/api/password/change',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['currentPassword' => 'test-password', 'newPassword' => 'Ab1!'])
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testChangeRejectsPasswordWithoutUppercase(): void
    {
        $user = $this->makeUser('pwtest2@test.local', ['ROLE_STAFF'], null);

        $this->httpClient->loginUser($user);
        $this->httpClient->request(
            'POST',
            '/api/password/change',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['currentPassword' => 'test-password', 'newPassword' => 'lowercase1!'])
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testChangeRejectsPasswordWithoutNumber(): void
    {
        $user = $this->makeUser('pwtest3@test.local', ['ROLE_STAFF'], null);

        $this->httpClient->loginUser($user);
        $this->httpClient->request(
            'POST',
            '/api/password/change',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['currentPassword' => 'test-password', 'newPassword' => 'NoNumbers!'])
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testChangeRejectsPasswordWithoutSpecialCharacter(): void
    {
        $user = $this->makeUser('pwtest4@test.local', ['ROLE_STAFF'], null);

        $this->httpClient->loginUser($user);
        $this->httpClient->request(
            'POST',
            '/api/password/change',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['currentPassword' => 'test-password', 'newPassword' => 'NoSpecial1'])
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testChangeRejectsWrongCurrentPassword(): void
    {
        $user = $this->makeUser('pwtest5@test.local', ['ROLE_STAFF'], null);

        $this->httpClient->loginUser($user);
        $this->httpClient->request(
            'POST',
            '/api/password/change',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['currentPassword' => 'wrong-password', 'newPassword' => 'ValidPass1!'])
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testChangeAcceptsValidPasswordAndPersistsIt(): void
    {
        $user = $this->makeUser('pwtest6@test.local', ['ROLE_STAFF'], null);
        $userId = $user->getId();

        $this->httpClient->loginUser($user);
        $this->httpClient->request(
            'POST',
            '/api/password/change',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['currentPassword' => 'test-password', 'newPassword' => 'ValidPass1!'])
        );

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());

        $this->em->clear();
        $reloaded = $this->em->getRepository(\App\Entity\Utilisateur::class)->find($userId);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($reloaded, 'ValidPass1!'), 'New password must actually be persisted and usable.');
        $this->assertFalse($reloaded->isMustChangePassword());
    }
}
