<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\UserType;
use App\Security\AccessCatalog;
use App\Service\SystemActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:platform:create-admin',
    description: 'Crée le premier super administrateur de la plateforme (à lancer après app:access:seed).',
)]
final class CreatePlatformAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SystemActor $systemActor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email (identifiant de connexion)')
            ->addArgument('firstname', InputArgument::REQUIRED, 'Prénom')
            ->addArgument('lastname', InputArgument::REQUIRED, 'Nom');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse email invalide.');

            return Command::FAILURE;
        }

        if (null !== $this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $io->error('Un compte utilise déjà cette adresse email.');

            return Command::FAILURE;
        }

        $role = $this->em->getRepository(Role::class)->findOneBy(['code' => AccessCatalog::SUPER_ADMIN]);

        if (!$role instanceof Role) {
            $io->error(sprintf('Le rôle %s n\'existe pas : lancez d\'abord « php bin/console app:access:seed ».', AccessCatalog::SUPER_ADMIN));

            return Command::FAILURE;
        }

        $phone = trim((string) $io->ask('Téléphone', '+243000000000'));
        $password = (string) $io->askHidden('Mot de passe (8 caractères minimum, avec une lettre et un chiffre)');

        if (1 !== preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
            $io->error('Mot de passe trop faible.');

            return Command::FAILURE;
        }

        if ($password !== (string) $io->askHidden('Confirmez le mot de passe')) {
            $io->error('Les mots de passe ne correspondent pas.');

            return Command::FAILURE;
        }

        $admin = (new User())
            ->setFirstname((string) $input->getArgument('firstname'))
            ->setLastname((string) $input->getArgument('lastname'))
            ->setPhone($phone)
            ->setEmail($email)
            ->setUserType(UserType::Platform)
            ->setCode('USR-' . strtoupper(bin2hex(random_bytes(4))))
            ->setIsActive(true)
            ->setIsLegalAccepted(true)
            ->setLegalAcceptedAt(new \DateTimeImmutable());
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
        $this->em->persist($admin);

        $this->em->persist((new UserRole())->setUser($admin)->setRole($role)->setAssignedBy($this->systemActor->get()));
        $this->em->flush();

        $io->success(sprintf('Super administrateur %s créé.', $email));

        return Command::SUCCESS;
    }
}
