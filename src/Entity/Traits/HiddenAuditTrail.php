<?php

namespace App\Entity\Traits;

use App\Entity\User;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * AuditTrail variant for embedded translation objects.
 * Keeps full audit functionality but hides all audit fields from serialization.
 */
trait HiddenAuditTrail
{
    use AuditTrail {
        getCreatedAt as private _traitGetCreatedAt;
        getUpdatedAt as private _traitGetUpdatedAt;
        getCreatedBy as private _traitGetCreatedBy;
        getUpdatedBy as private _traitGetUpdatedBy;
        getPublishedAt as private _traitGetPublishedAt;
        getDeletedAt as private _traitGetDeletedAt;
    }

    #[Ignore] public function getCreatedAt(): \DateTimeImmutable { return $this->_traitGetCreatedAt(); }
    #[Ignore] public function getUpdatedAt(): \DateTimeImmutable { return $this->_traitGetUpdatedAt(); }
    #[Ignore] public function getCreatedBy(): ?User { return $this->_traitGetCreatedBy(); }
    #[Ignore] public function getUpdatedBy(): ?User { return $this->_traitGetUpdatedBy(); }
    #[Ignore] public function getPublishedAt(): ?\DateTimeImmutable { return $this->_traitGetPublishedAt(); }
    #[Ignore] public function getDeletedAt(): ?\DateTimeImmutable { return $this->_traitGetDeletedAt(); }
}
