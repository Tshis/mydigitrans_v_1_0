<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Erreur métier d'une inscription partenaire : le message est affiché tel quel,
 * sous le champ concerné si $field est renseigné.
 */
final class PartnerRegistrationException extends \RuntimeException
{
    public function __construct(string $message, private readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public function getField(): ?string
    {
        return $this->field;
    }
}
