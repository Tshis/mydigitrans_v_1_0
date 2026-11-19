<?php

declare(strict_types=1);

namespace App\Dto\admin\agency;

use App\Entity\Branch;
use App\Entity\CashRegister;
use App\Entity\Currency;
use App\Enum\ExpenseType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ExpenseCreateData
{
    #[Assert\NotNull]
    public ExpenseType $type = ExpenseType::Other;

    #[Assert\NotNull(message: 'Veuillez sélectionner la succursale concernée.')]
    public ?Branch $branch = null;

    #[Assert\NotBlank(message: 'Le libellé de la dépense est obligatoire.')]
    #[Assert\Length(max: 150)]
    public ?string $name = null;

    #[Assert\NotNull(message: 'Veuillez saisir le montant de la dépense.')]
    #[Assert\Positive(message: 'Le montant doit être supérieur à 0.')]
    public ?float $amount = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner la devise.')]
    public ?Currency $currency = null;

    public ?\DateTimeImmutable $validFrom = null;

    public ?\DateTimeImmutable $validUntil = null;

    #[Assert\Choice(choices: ['bank', 'cash_register'])]
    public string $fundingSource = 'bank';

    public ?CashRegister $cashRegister = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\Callback]
    public function validateExpenseRules(ExecutionContextInterface $context): void
    {
        if (ExpenseType::Subscription === $this->type) {
            if (null === $this->validFrom) {
                $context->buildViolation('La date de début de validité est obligatoire pour un abonnement.')
                    ->atPath('validFrom')
                    ->addViolation();
            }
            if (null === $this->validUntil) {
                $context->buildViolation('La date de fin de validité est obligatoire pour un abonnement.')
                    ->atPath('validUntil')
                    ->addViolation();
            }
            if (null !== $this->validFrom && null !== $this->validUntil && $this->validUntil < $this->validFrom) {
                $context->buildViolation('La date de fin doit être postérieure à la date de début.')
                    ->atPath('validUntil')
                    ->addViolation();
            }
        }

        if ('cash_register' === $this->fundingSource && null === $this->cashRegister) {
            $context->buildViolation('Veuillez sélectionner le guichet de caisse qui décaissera les espèces.')
                ->atPath('cashRegister')
                ->addViolation();
        }
    }
}
