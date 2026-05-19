<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\AuditTrail;
use App\Entity\Traits\UuidIdentity;
use App\Enum\ProjectRole;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_project_member_user', columns: ['project_id', 'user_id'])]
#[ORM\Index(columns: ['project_id'], name: 'idx_project_member_project')]
#[ORM\Index(columns: ['user_id'], name: 'idx_project_member_user')]
#[ApiResource(
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']],
    security: "is_granted('ROLE_ADMIN')"
)]
class ProjectMember
{
    use AuditTrail;
    use UuidIdentity;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ?User $user = null;

    #[ORM\Column(enumType: ProjectRole::class)]
    #[Groups(['read', 'write'])]
    private ProjectRole $role = ProjectRole::Viewer;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeAuditTrail();
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;

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

    public function getRole(): ProjectRole
    {
        return $this->role;
    }

    public function setRole(ProjectRole $role): self
    {
        $this->role = $role;

        return $this;
    }
}
