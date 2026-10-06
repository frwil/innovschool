<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter « perm » : autorise une action si l'utilisateur porte un rôle qui a
 * la permission (subject = code, ex. users.view). ROLE_SUPER_ADMIN passe
 * systématiquement. Filet anti-lockout : si aucune ligne de rôle n'existe
 * en base (sync pas encore joué, ex. seconde machine) ou si les tables
 * access_* manquent encore (schema:update pas encore joué), ROLE_ADMIN
 * conserve l'accès total (comportement hérité).
 */
class PermissionVoter extends Voter
{
    public const ATTRIBUTE = 'perm';

    /** Cache mémoire par requête : les changements s'appliquent dès la requête suivante. */
    private array $cache = [];

    public function __construct(
        private PermissionRepository $permissionRepository,
        private RoleRepository $roleRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute && is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $key = $user->getId() . '|' . md5(serialize($user->getRoles()));
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->resolvePermissionNames($user->getRoles());
        }

        $names = $this->cache[$key];

        return in_array('*', $names, true) || in_array($subject, $names, true);
    }

    /**
     * @param list<string> $roleNames
     * @return list<string>
     */
    private function resolvePermissionNames(array $roleNames): array
    {
        try {
            // Filet anti-lockout : seed pas encore joué → ROLE_ADMIN garde tout.
            if ($this->roleRepository->count([]) === 0) {
                return in_array('ROLE_ADMIN', $roleNames, true) ? ['*'] : [];
            }

            return $this->permissionRepository->findNamesByRoleNames($roleNames);
        } catch (TableNotFoundException) {
            // Tables access_* absentes (seconde machine avant schema:update) :
            // même filet anti-lockout, l'app reste utilisable jusqu'au sync.
            return in_array('ROLE_ADMIN', $roleNames, true) ? ['*'] : [];
        }
    }
}
