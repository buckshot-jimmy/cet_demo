<?php

namespace App\Security\Voter;

use App\Entity\Factura;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class FacturaVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['VIEW', 'ADD']) && $subject instanceof Factura;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        $rol = $user->getRole()->getDenumire();

        switch ($attribute) {
            case 'ADD':
            case 'VIEW':
                return in_array($rol, ['ROLE_Administrator', 'ROLE_Medic', 'ROLE_Psiholog', 'ROLE_Receptioner']);
        }

        return false;
    }
}
