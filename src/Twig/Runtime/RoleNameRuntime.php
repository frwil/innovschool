<?php

namespace App\Twig\Runtime;

use App\Contract\UserRoleEnum;
use App\Repository\RoleRepository;
use Twig\Extension\RuntimeExtensionInterface;

class RoleNameRuntime implements RuntimeExtensionInterface
{
    public function __construct(private RoleRepository $roleRepository)
    {
    }

    public function doConvert($value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        $names = array_map(fn ($role) => $this->convertOne($role), $value);

        return implode(', ', array_filter($names));
    }

    /** Libellé FR d'un rôle : enum historique, sinon label du rôle en base, sinon le code brut. */
    private function convertOne(string $role): string
    {
        $title = UserRoleEnum::getTitleFrom($role);
        if ($title) {
            return $title;
        }
        $roleEntity = $this->roleRepository->findOneBy(['name' => $role]);
        if ($roleEntity) {
            return $roleEntity->getLabel();
        }

        return $role;
    }
}
