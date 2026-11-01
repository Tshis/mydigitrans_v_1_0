<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Erreurs métier de la gestion des utilisateurs. Chaque message est affiché sous son champ
 * (clé = nom du champ du formulaire) ; la clé « _global » vise le formulaire entier.
 */
final class AgentManagementException extends \RuntimeException
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
