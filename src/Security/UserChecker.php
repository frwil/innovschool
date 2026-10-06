<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque la connexion (et la session en cours) d'un compte explicitement
 * désactivé (enabled = false). La valeur null — comptes jamais renseignés —
 * est tolérée pour ne pas verrouiller les comptes existants.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && false === $user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException('Compte désactivé. Contactez l\'établissement.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
