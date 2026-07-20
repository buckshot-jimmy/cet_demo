<?php

namespace App\Services;

use App\Entity\Consultatie;
use App\Entity\Pacient;
use App\Entity\Role;
use App\Entity\Specialitate;
use App\Entity\Titulatura;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminService
{
    const PAROLA_SCHIMBATA_PRIMA_LOGARE = 1;
    const PAROLA_NESCHIMBATA_PRIMA_LOGARE = 0;

    public function __construct(
        private EntityManagerInterface $em,
        private TranslatorInterface $translator
    ) {}

    public function getNomenclatoareMedicale(): array
    {
        try {
            $roluri = $this->em->getRepository(Role::class)->getAllRoles();
            $specialitati = $this->em->getRepository(Specialitate::class)->findAll();
            $titulaturi = $this->em->getRepository(Titulatura::class)->findAll();
        } catch (\Exception $exception) {
            throw new \Exception("Data collection error", 4001, $exception);
        }

        return [
            'roluri' => $roluri,
            'specialitati' => $specialitati,
            'titulaturi' => $titulaturi
        ];
    }

    public function getLoggedUserData(UserInterface $loggedUser): array
    {
        $userData = [
            'id' => $loggedUser->getId(),
            'nume' => $loggedUser->getNume(),
            'prenume' => $loggedUser->getPrenume(),
            'telefon' => $loggedUser->getTelefon(),
            'email' => $loggedUser->getEmail(),
            'titulatura' => $loggedUser->getTitulatura() ? $loggedUser->getTitulatura()->getId() : null,
            'specialitate' => $loggedUser->getSpecialitate() ? $loggedUser->getSpecialitate()->getId() : null,
            'codParafa' => $loggedUser->getCodParafa(),
            'rol_id' => $loggedUser->getRole() ? $loggedUser->getRole()->getId() : null,
            'rol' => $loggedUser->getRole() ? $loggedUser->getRole()->getDenumire() : null,
            'username' => $loggedUser->getUsername(),
            'parolaSchimbata' => $loggedUser->getParolaSchimbata()
                ? self::PAROLA_SCHIMBATA_PRIMA_LOGARE
                : self::PAROLA_NESCHIMBATA_PRIMA_LOGARE
        ];

        return $userData;
    }

    public function getValoriGrafice(array $userData): array
    {
        $filterValoare = [];

        if (in_array($userData['rol'], ['ROLE_Medic', 'ROLE_Psiholog'])) {
            $filterValoare['medic'] = $userData['id'];
        }

        try {
            $valoareServicii['total'] = $this->em->getRepository(Consultatie::class)->valoareServicii($filterValoare);
            $filterValoare['luna'] = date('m');
            $filterValoare['an'] = date('Y');
            $valoareServicii['luna'] = $this->em->getRepository(Consultatie::class)->valoareServicii($filterValoare);
        } catch (\Exception $exception) {
            throw new \Exception("Data collection error", 4001, $exception);
        }

        return $valoareServicii;
    }

    public function getTotaluriPacienti(array $userData): array
    {
        $valoriCabinet = [
            'nrTotalPacienti' => $this->em->getRepository(Pacient::class)->count([]),
            'nrTotalPrestatii' => $this->em->getRepository(Consultatie::class)->count([])
        ];

        try {
            $consultatiiPacientiMedic['nrPacientiMedic'] =
                $this->em->getRepository(Consultatie::class)->getNrPacientiConsultatiDeMedic($userData['id']);
            $consultatiiPacientiMedic['nrServiciiMedic'] =
                $this->em->getRepository(Consultatie::class)->getNrServiciiPrestateMedic($userData['id']);

            return [
                'consultatiiPacientiMedic' => $consultatiiPacientiMedic,
                'valoriCabinet' => $valoriCabinet
            ];
        } catch (\Exception $exception) {
            throw new \Exception("Data collection error", 4001, $exception);
        }
    }

    public function setSessionInfo(SessionInterface $session, $params = []): SessionInterface
    {
        foreach ($params as $name => $value) {
            $session->set($name, $value);
        }

        return $session;
    }

    public function buildValidationErrors($errors): string
    {
        $messages = '';

        foreach ($errors as $error) {
            $messages .= " " . $this->translator->trans($error->getMessage());
        }

        return $messages;
    }
}
