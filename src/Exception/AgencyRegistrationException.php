<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Erreurs métier d'une inscription d'agence. Chaque message est affiché sous son champ
 * (clé = nom du champ du formulaire) ; la clé « _global » vise le formulaire entier.
 */
final class AgencyRegistrationException extends \RuntimeException
{
    public const GLOBAL = '_global';

    /**
     * @param array<string, string> $errors champ => message
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
