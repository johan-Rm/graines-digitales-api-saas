<?php

namespace App\Entity;

use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\UuidIdentity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
#[ORM\Index(columns: ['email'], name: 'idx_user_email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use AuditTrail;
    use UuidIdentity;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Email]
    #[Groups(['read'])]
    private string $email;

    #[ORM\Column]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    public function __construct(string $email = '')
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower($email);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}
