<?php

declare(strict_types=1);

namespace App\Controller\web\admin;

use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\User;
use App\Enum\Scope;
use App\Repository\PermissionRepository;
use App\Security\AccessCatalog;
use App\Security\Voter\RoleVoter;
use App\Service\RoleDirectory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractRoleAdminController extends AbstractController
{
    abstract protected function scope(): Scope;

    protected function listAction(RoleDirectory $roleDirectory): Response
    {
        $scope = $this->scope();
        $roles = $roleDirectory->rolesOf($scope);
        $isAgency = (Scope::Agency === $scope);

        return $this->render('admin/role/index.html.twig', [
            'page'        => 'job title',
            'roles'       => $roles,
            'index_route' => $isAgency ? 'admin_agency_role_index' : 'admin_platform_role_index',
            'add_route'   => $isAgency ? 'admin_agency_role_add' : 'admin_platform_role_add',
            'edit_route'  => $isAgency ? 'admin_agency_role_edit' : 'admin_platform_role_edit',
            'isAgency'    => $isAgency,
        ]);
    }

    protected function showAction(EntityManagerInterface $em, Role $role): Response
    {
        $this->denyAccessUnlessGranted(RoleVoter::VIEW, $role);
        $scope = $this->scope();
        $isAgency = (Scope::Agency === $scope);

        $rolePermissions = $em->getRepository(RolePermission::class)->findBy([
            'role'      => $role,
            'revokedAt' => null
        ]);

        $groups = [];
        foreach ($rolePermissions as $rp) {
            $perm = $rp->getPermission();
            if ($perm && ($perm->getScope() instanceof Scope ? $perm->getScope()->value : (string) $perm->getScope()) === $scope->value) {
                $module = (string) $perm->getModule();

                if (!isset($groups[$module])) {
                    $meta = AccessCatalog::moduleMeta($scope, $module);
                    $groups[$module] = [
                        'module'      => $module,
                        'label'       => $meta['label'] ?? ucfirst($module),
                        'icon'        => $meta['icon'] ?? 'fa-shield-halved',
                        'permissions' => []
                    ];
                }

                $groups[$module]['permissions'][] = [
                    'code' => (string) $perm->getCode(),
                    'name' => (string) $perm->getName()
                ];
            }
        }

        return $this->render('admin/role/show.html.twig', [
            'page'        => 'job title',
            'role'        => $role,
            'modules'     => array_values($groups),
            'index_route' => $isAgency ? 'admin_agency_role_index' : 'admin_platform_role_index',
        ]);
    }

    protected function formAction(
        Request $request,
        PermissionRepository $permissionRepository,
        EntityManagerInterface $em,
        ?Role $role = null
    ): Response {

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non connecté ou invalide.');
        }

        $isEdit = ($role !== null);
        $scope = $this->scope();
        $isAgency = (Scope::Agency === $scope);

        if (!$isEdit) {
            $role = new Role();
            $role->setScope($scope);
            $role->setIsActive(true);
        } else {
            $this->denyAccessUnlessGranted(RoleVoter::EDIT, $role);
        }

        if ($request->isMethod('POST')) {
            $submittedToken = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('role_secure_token', $submittedToken)) {
                throw new BadRequestHttpException('Jeton CSRF invalide.');
            }

            $name = trim((string) $request->request->get('name'));
            $description = trim((string) $request->request->get('description'));

            if (empty($name)) {
                $this->addFlash('error', 'L’intitulé est obligatoire.');
                return $this->redirectToRoute($isAgency ? 'admin_agency_role_index' : 'admin_platform_role_index');
            }

            $role->setName($name);
            $role->setDescription($description);
            $role->setIsActive($request->request->get('is_active') === '1');
            $role->setCode('ROLE_' . strtoupper(str_replace(' ', '_', $name)));

            $em->persist($role);

            $submittedPerms = $request->request->all('perms');
            $allowedAccessModes = ['none', 'read', 'write', 'read_write'];

            if ($isEdit) {
                $criteria = ['role' => $role];
                if ($isAgency) {
                    $criteria['agency'] = $user->getAgency();
                }
                $oldPermissions = $em->getRepository(RolePermission::class)->findBy($criteria);
                foreach ($oldPermissions as $oldPerm) {
                    $em->remove($oldPerm);
                }
                $em->flush();
            }

            foreach ($submittedPerms as $moduleKey => $accessValue) {
                if (!in_array($accessValue, $allowedAccessModes, true)) {
                    throw new BadRequestHttpException('Mode d’accès invalide.');
                }

                if ($accessValue === 'none') {
                    continue;
                }

                $allModulePermissions = $permissionRepository->findBy([
                    'module' => $moduleKey,
                    'scope'  => $scope->value
                ]);

                foreach ($allModulePermissions as $permission) {
                    $code = strtolower((string) $permission->getCode());
                    $shouldSave = false;

                    if ($accessValue === 'read') {
                        $shouldSave = (str_contains($code, 'view') || str_contains($code, 'index') || str_contains($code, 'show') || str_contains($code, 'read'));
                    } elseif ($accessValue === 'write') {
                        $shouldSave = (str_contains($code, 'create') || str_contains($code, 'edit') || str_contains($code, 'update') || str_contains($code, 'delete') || str_contains($code, 'write'));
                    } elseif ($accessValue === 'read_write') {
                        $shouldSave = true;
                    }

                    if ($shouldSave) {
                        $rolePermission = new RolePermission();
                        $rolePermission->setRole($role);
                        $rolePermission->setPermission($permission);

                        // 🟢 FIX NOT NULL : Hydratation obligatoire de l'utilisateur affectant l'habilitation
                        $rolePermission->setAssignedBy($user); //

                        if ($isAgency) {
                            $rolePermission->setAgency($user->getAgency());
                            if ($user->getBranch()) {
                                $rolePermission->setBranch($user->getBranch());
                            }
                        }
                        $em->persist($rolePermission);
                    }
                }
            }

            $em->flush();
            $this->addFlash('success', 'Habilitations enregistrées de manière sécurisée.');

            return $this->redirectToRoute($isAgency ? 'admin_agency_role_index' : 'admin_platform_role_index');
        }

        $availablePermissions = $permissionRepository->findBy(['scope' => $scope->value]);

        $matrixRows = [];
        foreach ($availablePermissions as $perm) {
            $moduleKey = (string) $perm->getModule();
            if (empty($moduleKey)) {
                continue;
            }

            if (!isset($matrixRows[$moduleKey])) {
                $meta = AccessCatalog::moduleMeta($scope, $moduleKey);
                $matrixRows[$moduleKey] = [
                    'key'         => $moduleKey,
                    'label'       => $meta['label'] ?? ucfirst($moduleKey),
                    'description' => $meta['description'] ?? 'Gestion du module ' . $moduleKey,
                    'icon'        => $meta['icon'] ?? 'fa-shield-halved',
                    'has_view'    => false,
                    'has_write'   => false,
                ];
            }

            if ($isEdit) {
                $criteria = ['role' => $role, 'permission' => $perm];
                if ($isAgency) {
                    $criteria['agency'] = $user->getAgency();
                }
                $hasLink = $em->getRepository(RolePermission::class)->findOneBy($criteria);

                if ($hasLink) {
                    $code = strtolower((string) $perm->getCode());
                    if (str_contains($code, 'view') || str_contains($code, 'index') || str_contains($code, 'show') || str_contains($code, 'read')) {
                        $matrixRows[$moduleKey]['has_view'] = true;
                    }
                    if (str_contains($code, 'create') || str_contains($code, 'edit') || str_contains($code, 'update') || str_contains($code, 'delete') || str_contains($code, 'write')) {
                        $matrixRows[$moduleKey]['has_write'] = true;
                    }
                }
            }
        }

        foreach ($matrixRows as $key => $row) {
            if ($row['has_view'] && $row['has_write']) {
                $matrixRows[$key]['value'] = 'read_write';
            } elseif ($row['has_view']) {
                $matrixRows[$key]['value'] = 'read';
            } elseif ($row['has_write']) {
                $matrixRows[$key]['value'] = 'write';
            } else {
                $matrixRows[$key]['value'] = 'none';
            }
        }
        return $this->render('admin/role/form.html.twig', [
            'page'        => 'job title',
            'isEdit'      => $isEdit,
            'role'        => $role,
            'modules'     => array_values($matrixRows),
            'index_route' => $isAgency ? 'admin_agency_role_index' : 'admin_platform_role_index',
            'submit_url'  => $isEdit
                ? $this->generateUrl($isAgency ? 'admin_agency_role_edit' : 'admin_platform_role_edit', ['id' => $role->getId()])
                : $this->generateUrl($isAgency ? 'admin_agency_role_add' : 'admin_platform_role_add'),
        ]);
    }
}
