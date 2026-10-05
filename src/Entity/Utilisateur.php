<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\ManyToOne(targetEntity: Bureau::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Bureau $bureau = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $mustChangePassword = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastPasswordChangeAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $failedPasswordAttempts = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordLockedUntil = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureBlob = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Client-only accounts must NOT inherit ROLE_USER (which grants admin API access).
        // All other users (staff, admin) get ROLE_USER as the base authenticated role.
        if (!in_array('ROLE_CLIENT', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Erase credentials
     *
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getBureau(): ?Bureau
    {
        return $this->bureau;
    }

    public function setBureau(?Bureau $bureau): static
    {
        $this->bureau = $bureau;

        return $this;
    }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): static { $this->photo = $photo; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function isMustChangePassword(): bool { return $this->mustChangePassword; }
    public function setMustChangePassword(bool $v): static { $this->mustChangePassword = $v; return $this; }

    public function getLastPasswordChangeAt(): ?\DateTimeImmutable { return $this->lastPasswordChangeAt; }
    public function setLastPasswordChangeAt(?\DateTimeImmutable $v): static { $this->lastPasswordChangeAt = $v; return $this; }

    public function getFailedPasswordAttempts(): int { return $this->failedPasswordAttempts; }
    public function setFailedPasswordAttempts(int $v): static { $this->failedPasswordAttempts = $v; return $this; }

    public function getPasswordLockedUntil(): ?\DateTimeImmutable { return $this->passwordLockedUntil; }
    public function setPasswordLockedUntil(?\DateTimeImmutable $v): static { $this->passwordLockedUntil = $v; return $this; }

    public function getCreeAu(): ?\DateTimeImmutable
    {
        return $this->creeAu;
    }

    public function setCreeAu(\DateTimeImmutable $creeAu): static
    {
        $this->creeAu = $creeAu;

        return $this;
    }

    public function getEditAu(): ?\DateTimeImmutable
    {
        return $this->editAu;
    }

    public function setEditAu(?\DateTimeImmutable $editAu): static
    {
        $this->editAu = $editAu;

        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable { return $this->lastActivityAt; }
    public function setLastActivityAt(?\DateTimeImmutable $v): static { $this->lastActivityAt = $v; return $this; }

    public function getSignatureBlob(): ?string { return $this->signatureBlob; }
    public function setSignatureBlob(?string $v): static { $this->signatureBlob = $v; return $this; }
}