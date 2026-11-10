<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Enum\Scope;
use App\Security\AccessCatalog;
use App\Service\SystemActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:access:seed',
    description: 'Initialise et synchronise la matrice des rôles et des permissions système sous la signature de l\'acteur système.',
)]
final class SeedAccessCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SystemActor $systemActor
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation du catalogue AccessCatalog MyDigitrans');

        $robot = $this->systemActor->get();
        $io->text(sprintf('✓ Traçabilité signée par l\'acteur : <info>%s</info>', $robot->getEmail()));

        $io->section('1. Synchronisation des permissions system (MCD Table 6)');
        $permissionEntities = [];

        foreach ([Scope::Agency, Scope::Platform] as $scope) {
            $catalogPermissions = AccessCatalog::permissions($scope);

            foreach (array_keys($catalogPermissions) as $permissionKey) {
                $uniqueDbCode = $scope->value . ':' . $permissionKey;

                $permission = $this->em->getRepository(Permission::class)->findOneBy(['code' => $uniqueDbCode]);
                if (!$permission) {
                    // 🟢 FIX PROTOCOLE : On isole la chaîne brute du module (ex: "currency")
                    $parts = explode('.', $permissionKey);
                    $moduleName = $parts[0] ?? $permissionKey;

                    $meta = AccessCatalog::moduleMeta($scope, $moduleName);

                    $permission = new Permission();
                    $permission->setCode($uniqueDbCode);
                    $permission->setScope($scope);
                    $permission->setModule($moduleName); // Transmet désormais une chaîne de caractères valide
                    $permission->setIcon($meta['icon'] ?? 'fa-circle-dot');
                    $permission->setName(sprintf('Action %s [%s]', $permissionKey, ucfirst($scope->value)));
                    $permission->setCreatedBy($robot);
                    $this->em->persist($permission);
                }
                $permissionEntities[$scope->value . '_' . $permissionKey] = $permission;
            }
        }

        $this->em->flush();
        $io->writeln('✓ Permissions système synchronisées de manière unique en base de données.');

        $io->section('2. Synchronisation de la matrice des droits (MCD Table 4 et 7)');

        foreach ([Scope::Agency, Scope::Platform] as $scope) {
            $rolesDefinitions = AccessCatalog::roles($scope);

            foreach ($rolesDefinitions as $roleCode => $meta) {
                $role = $this->em->getRepository(Role::class)->findOneBy([
                    'code' => $roleCode,
                    'scope' => $scope->value
                ]);

                if (!$role) {
                    $role = new Role();
                    $role->setCode($roleCode);
                    $role->setScope($scope);
                    $role->setIsActive(true);
                }
                $role->setName($meta['name']);
                $role->setDescription($meta['description']);
                $this->em->persist($role);
                $this->em->flush();

                $oldRelations = $this->em->getRepository(RolePermission::class)->findBy(['role' => $role]);
                foreach ($oldRelations as $oldRelation) {
                    $this->em->remove($oldRelation);
                }
                $this->em->flush();

                foreach ($meta['permissions'] as $permCode) {
                    $entityKey = $scope->value . '_' . $permCode;
                    if (isset($permissionEntities[$entityKey])) {
                        $rolePermission = new RolePermission();
                        $rolePermission->setRole($role);
                        $rolePermission->setPermission($permissionEntities[$entityKey]);
                        $rolePermission->setAssignedBy($robot);
                        $rolePermission->setAgency(null);
                        $rolePermission->setBranch(null);
                        $this->em->persist($rolePermission);
                    }
                }
                $this->em->flush();
            }
        }

        $io->success('Succès ! L\'administrateur d\'agence possède désormais l\'intégralité des privilèges de sa bulle SaaS.');
        return Command::SUCCESS;
    }
}
