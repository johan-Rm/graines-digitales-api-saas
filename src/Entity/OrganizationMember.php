<?php

namespace App\Entity;

use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\UuidIdentity;
use App\Enum\OrganizationRole;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_organization_member_user', columns: ['organization_id', 'user_id'])]
#[ORM\Index(columns: ['organization_id'], name: 'idx_organization_member_organization')]
#[ORM\Index(columns: ['user_id'], name: 'idx_organization_member_user')]
class OrganizationMember
{
    use AuditTrail;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?Organization $organization = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?User $user = null;

    #[ORM\Column(enumType: OrganizationRole::class)]
    #[Groups(['read', 'write'])]
    private OrganizationRole $role = OrganizationRole::Member;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): OrganizationRole
    {
        return $this->role;
    }

    public function setRole(OrganizationRole $role): self
    {
        $this->role = $role;

        return $this;
    }
}
