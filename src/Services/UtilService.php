<?php

namespace App\Services;

class UtilService
{
    public static function calculeazaDatePacient($cnp): array
    {
        $azi = new \DateTime();
        $sex = (int)$cnp[0];
        $an = (int)substr($cnp, 1, 2);
        $luna = (int)substr($cnp, 3, 2);
        $zi = (int)substr($cnp, 5, 2);

        if ($sex == 1 || $sex == 2) {
            $an += 1900;
        } elseif ($sex == 3 || $sex == 4) {
            $an += 1800;
        } elseif ($sex == 5 || $sex == 6) {
            $an += 2000;
        } elseif ($sex == 7 || $sex == 8) {
            if ((int)substr($cnp, 1, 1) === 0) {
                $an += 2000;
            } else {
                $an += 1900;
            }
        } else {
            return null;
        }

        $dataNasterii = new \DateTime("$an-$luna-$zi");
        $varsta = $azi->diff($dataNasterii)->y;

        return [
            'sex' => in_array($sex, [1, 3, 5, 7]) ? 'M' : 'F',
            'dataNasterii' => $dataNasterii->format('d-m-Y'),
            'varsta' => $varsta
        ];
    }

    public static function getDateFirma(): array
    {
        return [
            'denumire' => 'MIND RESET',
            'adresa' => 'Str. Septimiu Albini Nr. 49/2',
            'localitate' => 'Cluj-Napoca',
            'judet' => 'Cluj',
            'tara' => 'Romania',
            'codPostal' => 400437,
            'email' => 'programari@mindreset.ro',
            'telefon' => '0364 405 151, 0722 225 583'
        ];
    }
}
