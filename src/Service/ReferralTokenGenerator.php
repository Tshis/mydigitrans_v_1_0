<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Jeton de parrainage : MDT + 3 premières lettres du prénom + 3 chiffres (ex. MDTDAN718).
 * Si toutes les combinaisons à 3 chiffres sont prises pour ce préfixe, on passe à 4 puis 5 chiffres.
 */
final class ReferralTokenGenerator
{
    private const BRAND_PREFIX = 'MDT';

    /** Nombre de chiffres => nombre de tirages avant d'élargir. */
    private const ATTEMPTS = [3 => 30, 4 => 30, 5 => 30];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function generate(string $firstname): string
    {
        $letters = $this->letters($firstname);

        foreach (self::ATTEMPTS as $digits => $attempts) {
            for ($i = 0; $i < $attempts; ++$i) {
                $token = self::BRAND_PREFIX.$letters.random_int(10 ** ($digits - 1), 10 ** $digits - 1);

                if (!$this->exists($token)) {
                    return $token;
                }
            }
        }

        throw new \RuntimeException('Impossible de générer un code de parrainage unique.');
    }

    /**
     * Trois lettres majuscules sans accent ; « PRN » si le prénom n'a aucune lettre, complété par X si trop court.
     */
    private function letters(string $firstname): string
    {
        $ascii = (new AsciiSlugger())->slug($firstname, '')->toString();
        $letters = strtoupper(substr((string) preg_replace('/[^A-Za-z]/', '', $ascii), 0, 3));

        return str_pad('' === $letters ? 'PRN' : $letters, 3, 'X');
    }

    private function exists(string $token): bool
    {
        return null !== $this->em->getRepository(User::class)->findOneBy(['referralToken' => $token]);
    }
}
