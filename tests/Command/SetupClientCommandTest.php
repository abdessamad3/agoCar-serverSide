<?php

namespace App\Tests\Command;

use App\Command\SetupClientCommand;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Phase 12.3 Stage 1: SetupClientCommand is destructive (TRUNCATEs every
 * business table) and used to have a hardcoded admin email/password with no
 * safeguards at all. These tests cover the hardening: no hardcoded
 * credentials, a hard refusal in 'prod', a required confirmation before
 * wiping, and a safe, non-destructive failure when required input is
 * missing in non-interactive mode.
 */
final class SetupClientCommandTest extends KernelTestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get(Connection::class);

        // Safety check before touching anything: this suite must only ever
        // run against the dedicated test database.
        $dbName = $this->db->getDatabase();
        if (!str_contains($dbName, 'test')) {
            self::fail("Refusing to run -- connected database '$dbName' does not look like a test database.");
        }

        $this->db->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['utilisateur', 'bureau', 'company', 'parametres', 'conditions_contrat'] as $t) {
            $this->db->executeStatement("TRUNCATE TABLE `$t`");
        }
        $this->db->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function makeCommand(string $environment = 'test'): SetupClientCommand
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($environment);

        return new SetupClientCommand($this->db, $hasher, $kernel);
    }

    public function testRefusesToRunInProd(): void
    {
        $tester = new CommandTester($this->makeCommand('prod'));
        $exit = $tester->execute(
            ['--admin-email' => 'a@test.local', '--admin-password' => 'Password123'],
            ['interactive' => false]
        );

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('Refusing', $tester->getDisplay());

        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM utilisateur');
        $this->assertSame(0, $count, 'Nothing should have been written when refused for the prod environment.');
    }

    public function testFailsWithoutEmailWhenNonInteractive(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $exit = $tester->execute(['--admin-password' => 'Password123'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('admin-email', $tester->getDisplay());

        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM utilisateur');
        $this->assertSame(0, $count);
    }

    public function testFailsWithoutPasswordWhenNonInteractive(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $exit = $tester->execute(['--admin-email' => 'a@test.local'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('admin-password', $tester->getDisplay());
    }

    public function testRejectsAWeakPassword(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $exit = $tester->execute(
            ['--admin-email' => 'a@test.local', '--admin-password' => 'short'],
            ['interactive' => false]
        );

        $this->assertSame(Command::FAILURE, $exit);
    }

    public function testRejectsAnInvalidEmail(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $exit = $tester->execute(
            ['--admin-email' => 'not-an-email', '--admin-password' => 'Password123'],
            ['interactive' => false]
        );

        $this->assertSame(Command::FAILURE, $exit);
    }

    public function testDecliningConfirmationAbortsWithoutWiping(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $tester->setInputs(['no']);
        $exit = $tester->execute(['--admin-email' => 'a@test.local', '--admin-password' => 'Password123']);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('Aborted', $tester->getDisplay());

        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM utilisateur');
        $this->assertSame(0, $count, 'Declining the confirmation prompt must not create or delete anything.');
    }

    public function testForceSkipsConfirmationAndCompletesSetup(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $exit = $tester->execute([
            '--admin-email'    => 'newadmin@test.local',
            '--admin-password' => 'Password123',
            '--force'          => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringNotContainsString('Password123', $tester->getDisplay(), 'The password must never be echoed back.');

        $row = $this->db->fetchAssociative(
            "SELECT email, roles FROM utilisateur WHERE email = 'newadmin@test.local'"
        );
        $this->assertNotFalse($row, 'The admin account should have been created.');
        $this->assertSame(['ROLE_ADMIN'], json_decode($row['roles'], true));

        $bureauCount = (int) $this->db->fetchOne('SELECT COUNT(*) FROM bureau');
        $this->assertSame(1, $bureauCount);
    }
}
