<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PlatformRoleVoter extends Voter
{
    public function __construct(private readonly PlatformSuperAdminResolver $superAdminResolver)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->superAdminResolver->isSuperAdmin($token->getUser());
    }
}
