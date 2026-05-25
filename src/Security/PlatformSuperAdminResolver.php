<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class PlatformSuperAdminResolver
{
    /**
     * @var list<string>
     */
    private array $emails;

    public function __construct(string $superAdminEmails = '')
    {
        $this->emails = array_values(array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', $superAdminEmails),
        )));
    }

    public function isSuperAdmin(?UserInterface $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return in_array(strtolower($user->getEmail()), $this->emails, true);
    }
}
