<?php

declare(strict_types=1);

namespace App\Dto\public\agency;

use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\User;

/**
 * Ce que crée une inscription d'agence : la compagnie, son siège et son responsable général.
 */
final readonly class AgencyRegistrationResult
{
    public function __construct(
        public Agency $agency,
        public Branch $headquarters,
        public User $owner,
    ) {}
}
