<?php

namespace App\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Form\RoleType;
use App\Service\AccessRightsService;
use App\Service\OperationLogger;
use App\Service\PermissionCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/admin/roles')]
final class RoleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AccessRightsService $rights,
        private PermissionCatalog $catalog,
        private OperationLogger $operationLogger,
    ) {
    }

    #[Route('', name: 'app_role_index', methods: ['GET'])]
    public function index(): Response
    {
        // NB : un subject string de #[IsGranted] serait lu comme un nom
        // d'argument de contrôleur (Symfony 6.4) → check en corps de méthode.
        $this->denyAccessUnlessGranted('perm', 'roles.view');

        $actor = $this->getUser();
        assert($actor instanceof User);

        $roles = $this->em->getRepository(Role::class)->findAll();
        $rows = [];
        foreach ($roles as $role) {
            $rows[] = [
                'role' => $role,
                'carriers' => $this->rights->countRoleCarriers($role),
                'granted' => count($role->getGrantedPermissionNames()),
                'editable' => $this->rights->canEditRole($actor, $role),
            ];
        }

        return $this->render('roles/index.html.twig', [
            'rows' => $rows,
            'categoryLabels' => $this->catalog->getCategoryLabels(),
        ]);
    }

    #[Route('/new', name: 'app_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('perm', 'roles.manage');

        $role = new Role();
        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role->setName($this->normalizeRoleName($role->getName()));
            $existing = $this->em->getRepository(Role::class)->findOneBy(['name' => $role->getName()]);
            if ($existing) {
                $this->addFlash('danger', sprintf('Le rôle « %s » existe déjà.', $role->getName()));

                return $this->redirectToRoute('app_role_new');
            }

            $role->setIsSystem(false);
            $role->setLocked(false);
            $this->em->persist($role);
            $this->em->flush();
            $this->operationLogger->log(
                'Création du rôle ' . $role->getName(),
                'INFO',
                'Role',
                $role->getId()
            );
            $this->addFlash('success', sprintf('Rôle « %s » créé. Définissez maintenant ses permissions.', $role->getName()));

            return $this->redirectToRoute('app_role_edit', ['id' => $role->getId()]);
        }

        return $this->render('roles/form.html.twig', [
            'form' => $form->createView(),
            'role' => $role,
            'is_new' => true,
            'can_edit_matrix' => true,
            'permissions_by_category' => $this->catalog->getPermissions(),
            'category_labels' => $this->catalog->getCategoryLabels(),
            'matrix_state' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_role_edit', methods: ['GET', 'POST'])]
    public function edit(Role $role, Request $request): Response
    {
        // Consultation : roles.view ou roles.manage ; l'édition (POST) est
        // vérifiée par canEditRole (rôles verrouillés : superadmin seulement).
        if (!$this->isGranted('perm', 'roles.view') && !$this->isGranted('perm', 'roles.manage')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à consulter les rôles.');
        }

        $actor = $this->getUser();
        assert($actor instanceof User);

        $form = $this->createForm(RoleType::class, $role, ['lock_name' => $role->isSystem()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted('perm', 'roles.manage');

            // CSRF déjà validé par le formulaire (token Symfony).
            $granted = $request->request->all('permissions'); // list<string>
            $mandatory = $request->request->all('mandatory'); // list<string>
            try {
                $this->rights->applyRoleMatrix($actor, $role, is_array($granted) ? $granted : [], is_array($mandatory) ? $mandatory : []);
                $this->addFlash('success', sprintf('Permissions du rôle « %s » enregistrées.', $role->getName()));
            } catch (AccessDeniedException $e) {
                $this->addFlash('danger', $e->getMessage());
            }

            return $this->redirectToRoute('app_role_edit', ['id' => $role->getId()]);
        }

        return $this->render('roles/form.html.twig', [
            'form' => $form->createView(),
            'role' => $role,
            'is_new' => false,
            'can_edit_matrix' => $this->rights->canEditRole($actor, $role),
            'permissions_by_category' => $this->catalog->getPermissions(),
            'category_labels' => $this->catalog->getCategoryLabels(),
            'matrix_state' => $this->buildMatrixState($role),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_role_delete', methods: ['POST'])]
    public function delete(Role $role, Request $request): Response
    {
        $this->denyAccessUnlessGranted('perm', 'roles.manage');

        if (!$this->isCsrfTokenValid('role_delete', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_role_index');
        }

        $actor = $this->getUser();
        assert($actor instanceof User);

        try {
            $this->rights->assertCanDeleteRole($actor, $role);
        } catch (AccessDeniedException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_role_index');
        }

        $name = $role->getName();
        $this->em->remove($role);
        $this->em->flush();
        $this->operationLogger->log(
            'Suppression du rôle ' . $name,
            'INFO',
            'Role',
            null
        );
        $this->addFlash('success', sprintf('Rôle « %s » supprimé.', $name));

        return $this->redirectToRoute('app_role_index');
    }

    #[Route('/{id}/save-defaults', name: 'app_role_save_defaults', methods: ['POST'])]
    public function saveDefaults(Role $role, Request $request): Response
    {
        $this->denyAccessUnlessGranted('perm', 'roles.manage');

        return $this->applyDefaultAction($role, $request, 'saveDefaults', 'Permissions par défaut mémorisées.');
    }

    #[Route('/{id}/reset-defaults', name: 'app_role_reset_defaults', methods: ['POST'])]
    public function resetDefaults(Role $role, Request $request): Response
    {
        $this->denyAccessUnlessGranted('perm', 'roles.manage');

        return $this->applyDefaultAction($role, $request, 'resetToDefaults', 'Permissions réinitialisées aux valeurs par défaut.');
    }

    /**
     * POST JSON, même contrat que app_user_manage_edit :
     * { id, roles: [ROLE_…] } → { success } | { error }.
     */
    #[Route('/assign', name: 'app_role_assign', methods: ['POST'])]
    public function assign(Request $request): JsonResponse
    {
        $actor = $this->getUser();
        assert($actor instanceof User);

        $data = $request->request->all();
        if (!$this->isCsrfTokenValid('roles_assign', (string) ($data['_token'] ?? ''))) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }
        $target = isset($data['id']) ? $this->em->getRepository(User::class)->find((int) $data['id']) : null;
        if (!$target) {
            return $this->json(['error' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $roleNames = $data['roles'] ?? null;
        if (!is_array($roleNames)) {
            return $this->json(['error' => 'Aucun rôle soumis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->rights->applyRoles($actor, $target, array_values(array_map('strval', $roleNames)), [
                'school' => $request->getSession()->get('school_id'),
                'period' => $request->getSession()->get('period_id'),
            ]);
        } catch (AccessDeniedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            $this->operationLogger->log(
                'Erreur lors de l\'attribution de rôles à ' . $target->getFullName(),
                'ERROR',
                'User',
                $target->getId(),
                $e->getMessage()
            );

            return $this->json(['error' => 'Erreur lors de l\'enregistrement des rôles.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => 'Rôles mis à jour avec succès.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function applyDefaultAction(Role $role, Request $request, string $method, string $successMessage): Response
    {
        if (!$this->isCsrfTokenValid('role_defaults', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_role_edit', ['id' => $role->getId()]);
        }

        $actor = $this->getUser();
        assert($actor instanceof User);

        try {
            $this->rights->{$method}($actor, $role);
            $this->addFlash('success', $successMessage);
        } catch (AccessDeniedException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_role_edit', ['id' => $role->getId()]);
    }

    /** ROLE_CAISSIER — majuscules, préfixe ROLE_ si absent. */
    private function normalizeRoleName(string $name): string
    {
        $name = strtoupper(trim($name));
        if (!str_starts_with($name, 'ROLE_')) {
            $name = 'ROLE_' . $name;
        }

        return preg_replace('/[^A-Z0-9_]/', '_', $name) ?? $name;
    }

    /**
     * État de la matrice : name => [granted, mandatory, default].
     *
     * @return array<string, array{granted: bool, mandatory: bool, default: bool}>
     */
    private function buildMatrixState(Role $role): array
    {
        $state = [];
        foreach ($this->catalog->getAllPermissionNames() as $name) {
            $state[$name] = ['granted' => false, 'mandatory' => false, 'default' => false];
        }
        foreach ($role->getRolePermissions() as $row) {
            $name = $row->getPermission()?->getName();
            if ($name === null) {
                continue;
            }
            $state[$name] = [
                'granted' => true,
                'mandatory' => $row->isMandatory(),
                'default' => $row->isDefault(),
            ];
        }

        return $state;
    }
}
