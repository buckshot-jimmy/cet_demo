<?php

namespace App\Security\Voter;

use App\Entity\Consultatie;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class ConsultatieVoter extends Voter
{
    const ROL_ADMIN = 'ROLE_Administrator';
    const ROL_MEDIC = 'ROLE_Medic';
    const ROL_PSIHOLOG = 'ROLE_Psiholog';
    const ROL_RECEPTIONER = 'ROLE_Receptioner';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, ['ADD_EDIT', 'DELETE', 'VIEW', 'CLOSE_ALL']) && $subject instanceof Consultatie;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        $rol = $user->getRole()->getDenumire();

        switch ($attribute) {
            case 'DELETE':
                return $rol === self::ROL_ADMIN;
            case 'ADD_EDIT':
                if (in_array($rol, [
                    self::ROL_ADMIN,
                    self::ROL_MEDIC,
                    self::ROL_PSIHOLOG,
                    self::ROL_RECEPTIONER
                ], true)) {
                    return $this->permiteAdaugareEditareWithCondition($subject, $user);
                }
            case 'VIEW':
                return in_array($rol, [
                    self::ROL_ADMIN,
                    self::ROL_MEDIC,
                    self::ROL_PSIHOLOG
                ], true);
            case 'CLOSE_ALL':
                return in_array($rol, [
                    self::ROL_ADMIN,
                    self::ROL_RECEPTIONER
                ], true);
        }

        return false;
    }

    private function permiteAdaugareEditareWithCondition($consultatie, UserInterface $user)
    {
        if ($consultatie->getId() === null) {
            return true;
        }

        if ($user->getRole()->getDenumire() === self::ROL_ADMIN) {
            return true;
        }

        if ($user->getId() === $consultatie->getPret()->getMedic()->getId()) {
            return true;
        }

        return false;
    }
}
