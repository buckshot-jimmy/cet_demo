<?php

namespace App\Repository;

use App\Entity\Consultatie;
use App\Entity\Pacient;
use App\Entity\Pret;
use App\Entity\Programare;
use App\Services\NomenclatoareService;
use App\Services\UtilService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @method Consultatie|null find($id, $lockMode = null, $lockVersion = null)
 * @method Consultatie|null findOneBy(array $criteria, array $orderBy = null)
 * @method Consultatie[]    findAll()
 * @method Consultatie[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConsultatieRepository extends ServiceEntityRepository
{
    public const TIP_CONSULTATIE = '0';
    public const TIP_INVESTIGATIE = '1';
    public const TIP_EVAL_PSIHO = 2;
    public const TIP_TOATE = '3';
    public const COL_NR_INREG = '1';
    public const COL_NUME_PACIENT = '3';
    public const COL_CNP_PACIENT = '4';
    public const COL_NUME_MEDIC = '5';
    public const COL_SERVICIU = '6';
    public const COL_OWNER = '7';
    public const COL_DATA_PREZENTARII = '15';
    public const NEINCASATA = 0;

    public function __construct(
        ManagerRegistry $registry,
        private EntityManagerInterface $em,
        private NomenclatoareService $service)
    {
        parent::__construct($registry, Consultatie::class);
    }

    public function getAllConsultatiiByFilter($filter): array
    {
        $query = $this->createQueryBuilder('consultatii')
            ->select('consultatii.id', 'medic.nume AS numeMedic', 'medic.prenume AS prenumeMedic',
                'serviciu.denumire AS denumireServiciu', 'pacient.nume AS numePacient', 'pacient.cnp',
                'pacient.prenume AS prenumePacient', 'consultatii.nrInreg', 'consultatii.tarif',
                "DATE_FORMAT(consultatii.dataConsultatie, '%d-%m-%Y') AS dataConsultatie", 'consultatii.incasata',
                'owner.denumire AS denumireOwner', 'serviciu.tip AS tipServiciu', 'consultatii.inchisa')
            ->leftJoin('consultatii.pret', 'pret')
            ->leftJoin('pret.serviciu', 'serviciu')
            ->leftJoin('pret.medic', 'medic')
            ->leftJoin('pret.owner', 'owner')
            ->leftJoin('consultatii.pacient', 'pacient');

        $this->applyFilters($query, $filter);

        if (isset($filter['sort'])) {
            $this->buildSort($filter['sort'], $query);
        }

        if (isset($filter['length']) && isset($filter['start'])) {
            $query->setMaxResults($filter['length'])
                ->setFirstResult($filter['start']);
        }

        try {
            $total = $this->getTotalConsultatiiByFilter($filter);
            $consultatii = $query->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4001, $exception);
        }

        return [
            'consultatii' => $consultatii,
            'total' => $total,
        ];
    }

    private function getTotalConsultatiiByFilter($filter): int
    {
        $totalQuery = $this->createQueryBuilder('consultatii')
            ->select('COUNT(consultatii.id) AS totalPreturi')
            ->leftJoin('consultatii.pret', 'pret')
            ->leftJoin('pret.serviciu', 'serviciu')
            ->leftJoin('pret.medic', 'medic')
            ->leftJoin('pret.owner', 'owner')
            ->leftJoin('consultatii.pacient', 'pacient');

        $this->applyFilters($totalQuery, $filter);

        return $totalQuery->getQuery()->getSingleScalarResult();
    }

    public function saveConsultatie($dto): int
    {
        $consultatie = $this->em->getRepository(Consultatie::class)->find($dto->id);

        if(!$consultatie) {
            throw new BadRequestHttpException("Missing ID");
        }

        $consultatie->setPret($this->em->getRepository(Pret::class)->find($dto->pret));
        $consultatie->setConsultatie($dto->consultatie);
        $consultatie->setDiagnostic($dto->diagnostic);
        $consultatie->setNrInreg($dto->nrInreg);
        $consultatie->setTratament($dto->tratament);
        $consultatie->setPacient($this->em->getRepository(Pacient::class)->find($dto->pacient));
        $consultatie->setTarif($dto->tarif);
        $consultatie->setLoc($dto->loc);
        if ($dto->medicTrimitator) {
            $consultatie->setMedicTrimitator(strtoupper($dto->medicTrimitator));
        }
        $consultatie->setStearsa(false);
        $consultatie->setAhc($dto->ahc);
        $consultatie->setApp($dto->app);
        $consultatie->setInvestigatiiUrmate($dto->investigatiiUrmate);
        $consultatie->setTratamenteUrmate($dto->tratamenteUrmate);
        $consultatie->setObservatii($dto->observatii);

        try {
            $this->em->persist($consultatie);
            $this->em->flush();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4001, $exception);
        }

        return $consultatie->getId();
    }

    public function saveInvestigatie($dto): int
    {
        $investigatie = $this->em->getRepository(Consultatie::class)->find($dto->id);

        if(!$investigatie) {
            throw new BadRequestHttpException("Missing ID");
        }

        $investigatie->setPret($this->em->getRepository(Pret::class)->find($dto->pret));
        $investigatie->setConsultatie($dto->rezultat);
        $investigatie->setNrInreg($dto->nrInreg);
        $investigatie->setTratament($dto->concluzie);
        $investigatie->setDiagnostic('');
        $investigatie->setPacient($this->em->getRepository(Pacient::class)->find($dto->pacient));
        $investigatie->setTarif($dto->tarif);
        $investigatie->setLoc($dto->loc);
        $investigatie->setMedicTrimitator(strtoupper($dto->medicTrimitator));
        $investigatie->setStearsa(false);
        $investigatie->setAhc($dto->ahc);
        $investigatie->setApp($dto->app);

        try {
            $this->em->persist($investigatie);
            $this->em->flush();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4001, $exception);
        }

        return $investigatie->getId();
    }

    public function saveEvaluarePsihologica($dto): int
    {
        $evalPsiho = $this->em->getRepository(Consultatie::class)->find($dto->id);

        if(!$evalPsiho) {
            throw new BadRequestHttpException("Missing ID");
        }

        $evalPsiho->setPret($this->em->getRepository(Pret::class)->find($dto->pret));
        $evalPsiho->setNrInreg($dto->nrInreg);
        $evalPsiho->setConsultatie($dto->obiectiv);
        $evalPsiho->setTratament($dto->recomandari);
        $evalPsiho->setDiagnostic($dto->concluzie);
        $evalPsiho->setPacient($this->em->getRepository(Pacient::class)->find($dto->pacient));
        $evalPsiho->setTarif($dto->tarif);
        $evalPsiho->setLoc($dto->loc);
        $evalPsiho->setMedicTrimitator(strtoupper($dto->medicTrimitator));
        $evalPsiho->setStearsa(false);

        $ceCuCe['cognitiv_ce'] = $dto->cognitiv_ce;
        $ceCuCe['cognitiv_cu_ce'] = $dto->cognitiv_cu_ce;
        $ceCuCe['comportamental_ce'] = $dto->comportamental_ce;
        $ceCuCe['comportamental_cu_ce'] = $dto->comportamental_cu_ce;
        $ceCuCe['personalitate_ce'] = $dto->personalitate_ce;
        $ceCuCe['personalitate_cu_ce'] = $dto->personalitate_cu_ce;
        $ceCuCe['psihofiziologic_ce'] = $dto->psihofiziologic_ce;
        $ceCuCe['psihofiziologic_cu_ce'] = $dto->psihofiziologic_cu_ce;
        $ceCuCe['relationare_ce'] = $dto->relationare_ce;
        $ceCuCe['relationare_cu_ce'] = $dto->relationare_cu_ce;
        $ceCuCe['subiectiv_ce'] = $dto->subiectiv_ce;
        $ceCuCe['subiectiv_cu_ce'] = $dto->subiectiv_cu_ce;

        $evalPsiho->setEvalPsiho(serialize($ceCuCe));

        try {
            $this->em->persist($evalPsiho);
            $this->em->flush();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4001, $exception);
        }

        return $evalPsiho->getId();
    }

    public function getConsultatieInvestigatieEvaluare($id)
    {
        try {
            $consultatie = $this->createQueryBuilder('consultatii')
                ->select('consultatii.id', 'serviciu.id AS serviciuId', 'medic.id AS medicId', 'consultatii.loc',
                    'consultatii.tarif', 'consultatii.nrInreg', 'consultatii.diagnostic', 'consultatii.tratament',
                    'consultatii.consultatie', "CONCAT(pacient.nume, ' ', pacient.prenume) AS numePacient",
                    'pacient.id AS pacientId', 'serviciu.tip AS tipServiciu', 'consultatii.medicTrimitator',
                    'owner.id AS ownerId', 'pret.id AS pretId', 'pacient.cnp', 'serviciu.denumire AS denumireServiciu',
                    'consultatii.ahc', 'consultatii.app', 'consultatii.inchisa', 'consultatii.tratamenteUrmate',
                    'consultatii.investigatiiUrmate', 'consultatii.observatii', 'consultatii.evalPsiho')
                ->leftJoin('consultatii.pret', 'pret')
                ->leftJoin('pret.serviciu', 'serviciu')
                ->leftJoin('pret.medic', 'medic')
                ->leftJoin('pret.owner', 'owner')
                ->leftJoin('consultatii.pacient', 'pacient')
                ->where('consultatii.id = :id')
                ->setParameter(':id', $id)
                ->getQuery()
                ->getResult(AbstractQuery::HYDRATE_ARRAY);
        } catch (\Exception $exception) {
            throw new \Exception("Data collection error", 4041, $exception);
        }

        if (!empty($consultatie[0]['evalPsiho'])) {
            $consultatie[0]['evalPsiho'] = unserialize($consultatie[0]['cnp']);
        }

        if ($consultatie[0]) {
            $datePacient = UtilService::calculeazaDatePacient($consultatie[0]['cnp']);
            $consultatie[0]['sex'] = $datePacient['sex'];
            $consultatie[0]['dataNasterii'] = $datePacient['dataNasterii'];
        }

        return $consultatie[0] ?? [];
    }

    public function getIstoricPacient($pacientId, $tipServiciu): array
    {
        $query = $this->createQueryBuilder('consultatii')
            ->select('consultatii.id', 'medic.nume', 'medic.prenume', 'serviciu.tip AS tipServiciu',
                "DATE_FORMAT(consultatii.dataConsultatie, '%d-%m-%Y') AS data", 'serviciu.denumire')
            ->leftJoin('consultatii.pret', 'pret')
            ->leftJoin('pret.serviciu', 'serviciu')
            ->leftJoin('pret.medic', 'medic')
            ->where('consultatii.pacient = :pacientId')
            ->andWhere('consultatii.stearsa = :stearsa');

        if (self::TIP_TOATE !== $tipServiciu) {
            $query->andWhere('serviciu.tip = :tipServiciu')
                ->setParameters([
                    ':tipServiciu' => intval($tipServiciu),
                    ':pacientId' => $pacientId,
                    ':stearsa' => false,
                ]);
        } else {
            $query->andWhere(
                $query->expr()->orX(
                    $query->expr()->eq('serviciu.tip', ':tipConsultatie'),
                    $query->expr()->eq('serviciu.tip', ':tipInvestigatie')
                ))->setParameters([
                    ':pacientId' => $pacientId,
                    ':stearsa' => false,
                    ':tipConsultatie' => self::TIP_CONSULTATIE,
                    ':tipInvestigatie' => self::TIP_INVESTIGATIE,
                ]);
        }

        try {
            $istoric = $query->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        } catch (\Exception $exception) {
            throw new \Exception("Data collection error", 4041, $exception);
        }

        return $istoric;
    }

    public function getIstoricConsultatiiPentruFisa($pacient, $medic, $tipServiciu): array
    {
        $query = $this->createQueryBuilder('consultatii')
            ->select('consultatii.id', "DATE_FORMAT(consultatii.dataConsultatie, '%d-%m-%Y') AS dataConsultatie",
                'SUBSTRING(consultatii.ahc, 1, 50) AS ahc', 'SUBSTRING(consultatii.app, 1, 50) AS app',
                'SUBSTRING(consultatii.diagnostic, 1, 300) AS diagnostic',
                'SUBSTRING(consultatii.consultatie, 1, 500) AS consultatie',
                'consultatii.nrInreg', 'owner.denumire AS denumireOwner', 'owner.cui AS cuiOwner', 'consultatii.loc',
                'SUBSTRING(consultatii.tratament, 1, 500) AS tratament')
            ->leftJoin('consultatii.pret', 'pret')
            ->leftJoin('pret.serviciu', 'serviciu')
            ->leftJoin('pret.medic', 'medic')
            ->leftJoin('pret.owner', 'owner')
            ->where('consultatii.pacient = :pacient')
            ->andWhere('consultatii.stearsa = :stearsa')
            ->andWhere('pret.medic = :medic');

        if (self::TIP_TOATE !== $tipServiciu) {
            $query->andWhere('serviciu.tip = :tipServiciu')
                ->setParameters([
                    ':tipServiciu' => intval($tipServiciu),
                    ':pacient' => $pacient,
                    ':stearsa' => false,
                    ':medic' => $medic,
                ]);
        } else {
            $query->andWhere(
                $query->expr()->orX(
                    $query->expr()->eq('serviciu.tip', ':tipConsultatie'),
                    $query->expr()->eq('serviciu.tip', ':tipInvestigatie')
                ))->setParameters([
                    ':pacient' => $pacient,
                    ':stearsa' => false,
                    ':tipConsultatie' => self::TIP_CONSULTATIE,
                    ':tipInvestigatie' => self::TIP_INVESTIGATIE,
                    ':medic' => $medic,
                ]);
        }

        try {
            $istoric = $query->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4041, $exception);
        }

        return $istoric;
    }

    public function deschideStergeConsultatii($pacientId, $programareId, $serviciiPreturi, $dataPrezentare): array
    {
        $serviciiExistente  = $this->getServiciiPacient(
            ['pacientId' => $pacientId, 'incasata' => self::NEINCASATA, 'dataPrezentare' => $dataPrezentare]
        );

        $preturiRepo = $this->em->getRepository(Pret::class);
        $pacientiRepo = $this->em->getRepository(Pacient::class);

        $pacient = $pacientiRepo->find($pacientId);

        $existenteMap = [];
        foreach ($serviciiExistente as $serviciu) {
            $existenteMap[(int)$serviciu['pretId']] = $serviciu['consultatieId'];
        }

        $salvate = [];
        $sterse  = [];

        $primaConsultatie = $this->findBy(['pacient' => $pacientId], null, 1);
        $primaConsultatie = $primaConsultatie[0] ?? null;

        if ($programareId) {
            $programare = $this->em->getRepository(Programare::class)->findOneBy(['id' => $programareId]);
        }

        foreach ($serviciiPreturi as $pretId) {
            $pretId = (int)$pretId;

            if (isset($existenteMap[$pretId])) {
                unset($existenteMap[$pretId]);
                continue;
            }

            $pret = $preturiRepo->find($pretId);

            $consultatie = new Consultatie();
            $consultatie->setPacient($pacient);
            $consultatie->setPret($pret);
            $consultatie->setDataConsultatie(new \DateTime());
            $consultatie->setTarif($pret->getPret());

            if (isset($programare)) {
                $consultatie->setProgramare($programare);
            }

            if (
                $primaConsultatie &&
                $pret->getServiciu()->getTip() !== self::TIP_EVAL_PSIHO
            ) {
                $consultatie->setAhc($primaConsultatie->getAhc());
                $consultatie->setApp($primaConsultatie->getApp());
            }

            $this->em->persist($consultatie);
            $salvate[] = $consultatie;
        }

        foreach ($existenteMap as $consultatieId) {
            $consultatie = $this->find($consultatieId);

            $consultatie->setStearsa(true);
            $this->em->persist($consultatie);
            $sterse[] = $consultatieId;
        }

        try {
            $this->em->flush();
        } catch (\Exception $e) {
            throw new \Exception('Failed operation', 4001, $e);
        }

        return [
            'salvate' => array_map(fn($c) => $c->getId(), $salvate),
            'sterse'  => $sterse,
        ];
    }

    public function getServiciiPacient($filter): array
    {
        $params = [
            ':stearsa' => false,
            ':pacientId' => $filter['pacientId'],
        ];

        $query = $this->createQueryBuilder('consultatii')
            ->select("CONCAT(medic.nume, ' ', medic.prenume) AS numeMedic",
                'serviciu.denumire AS denumireServiciu', 'consultatii.tarif AS pretServiciu',
                'owner.denumire AS denumireOwner', 'consultatii.id AS consultatieId',
                'owner.id AS ownerId', 'pret.id AS pretId', 'serviciu.tip AS tipServiciu', 'medic.id AS medicId',
                "DATE_FORMAT(consultatii.dataConsultatie, '%d-%m-%Y') AS dataConsultatie", 'consultatii.inchisa',)
            ->leftJoin('consultatii.pret', 'pret')
            ->leftJoin('pret.medic', 'medic')
            ->leftJoin('pret.serviciu', 'serviciu')
            ->leftJoin('pret.owner', 'owner')
            ->leftJoin('consultatii.pacient', 'pacient')
            ->where('consultatii.stearsa = :stearsa')
            ->andWhere('consultatii.pacient = :pacientId');

        if (isset($filter['inchisa'])) {
            $query->andWhere('consultatii.inchisa = :inchisa');

            $params[':inchisa'] = intval($filter['inchisa']);
        }

        if (isset($filter['incasata'])) {
            $query->andWhere('consultatii.incasata = :incasata');

            $params[':incasata'] = intval($filter['incasata']);
        }

        if (isset($filter['dataPrezentare'])) {
            $query->andWhere("DATE_FORMAT(consultatii.dataConsultatie, '%d-%m-%Y') = :dataPrezentare");

            $params[':dataPrezentare'] = $filter['dataPrezentare'];
        }

        $query->setParameters($params);

        try {
            $serviciiPacient = $query->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        } catch (\Exception $exception) {
            throw new \Exception("Data collection error", 4041, $exception);
        }

        return $serviciiPacient;
    }

    public function incaseazaConsultatii($ids)
    {
        foreach ($ids as $id) {
            $consultatie = $this->em->getRepository(Consultatie::class)->find($id);

            $consultatie->setIncasata(true);

            try {
                $this->em->persist($consultatie);
                $this->em->flush();
            } catch (\Exception $exception) {
                throw new \Exception("Failed operation", 4001, $exception);
            }
        }
    }

    public function calculeazaPlataColaborator($formData): float
    {
        $query = $this->createQueryBuilder('consultatii')
            ->select('SUM(consultatii.tarif) AS totalPlata')
            ->leftJoin('consultatii.pret', 'pret')
            ->leftJoin('pret.medic', 'medic')
            ->leftJoin('pret.owner', 'owner')
            ->where('medic.id = :medicId')
            ->andWhere('owner.id = :ownerId')
            ->andWhere('consultatii.inchisa = :inchisa')
            ->andWhere('consultatii.stearsa = :stearsa')
            ->andWhere('consultatii.incasata = :incasata')
            ->andWhere('consultatii.platitaColaborator = :platita')
            ->andWhere('MONTH(consultatii.dataConsultatie) = :luna')
            ->andWhere('YEAR(consultatii.dataConsultatie) = :an')
            ->setParameters([
                ':medicId' => $formData['medic'],
                ':ownerId' => $formData['owner'],
                ':inchisa' => true,
                ':stearsa' => false,
                ':incasata' => true,
                ':platita' => false,
                ':luna' => intval($formData['luna']),
                ':an' => $formData['an'],
            ]);

        try {
           $totalDePlata = $query->getQuery()->getSingleScalarResult();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4001, $exception);
        }

        return $totalDePlata;
    }

    public function getConsultatiiRaportColaborator($raportColaborator, $platita = null): array
    {
        $params = [
            ':medicId' => $raportColaborator->getMedic()->getId(),
            ':ownerId' => $raportColaborator->getOwner()->getId(),
            ':inchisa' => true,
            ':stearsa' => false,
            ':incasata' => true,
            ':luna' => array_search($raportColaborator->getLuna(), $this->service->getLunileAnului()),
            ':an' => $raportColaborator->getAn(),
        ];

        $query = $this->createQueryBuilder('consultatii')
            ->select('consultatii.id')
            ->leftJoin('consultatii.pret', 'pret')
            ->leftJoin('pret.medic', 'medic')
            ->leftJoin('pret.owner', 'owner')
            ->where('medic.id = :medicId')
            ->andWhere('owner.id = :ownerId')
            ->andWhere('consultatii.inchisa = :inchisa')
            ->andWhere('consultatii.stearsa = :stearsa')
            ->andWhere('consultatii.incasata = :incasata')
            ->andWhere('MONTH(consultatii.dataConsultatie) = :luna')
            ->andWhere('YEAR(consultatii.dataConsultatie) = :an')
            ->orderBy('consultatii.dataConsultatie');

        if (null !== $platita) {
            $query->andWhere('consultatii.platitaColaborator = :platita');
            $params['platita'] = $platita;
        }

        $query->setParameters($params);

        try {
            $consultatii = $query->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        } catch (\Exception $exception) {
            throw new \Exception("Data collection error", 4001, $exception);
        }

        return $consultatii;
    }

    public function inchideDeschide($id): int
    {
        $consInv = $this->em->getRepository(Consultatie::class)->find($id);

        $consInv->setInchisa(!$consInv->getInchisa());

        try {
            $this->em->persist($consInv);
            $this->em->flush();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4001, $exception);
        }

        return $id;
    }

    public function valoareServicii($filter): int
    {
        $query = $this->createQueryBuilder('consultatii')
            ->select('SUM(consultatii.tarif) AS valoare',
                'SUM(consultatii.tarif*pret.procentajMedic/100) AS comision')
            ->leftJoin('consultatii.pret', 'pret')
            ->leftJoin('pret.medic', 'medic')
            ->andWhere('consultatii.inchisa = :inchisa')
            ->andWhere('consultatii.stearsa = :stearsa')
            ->andWhere('consultatii.incasata = :incasata');

        $params = [
            ':inchisa' => true,
            ':stearsa' => false,
            ':incasata' => true,
        ];

        if (isset($filter['luna']) && isset($filter['an'])) {
            $query->andWhere('MONTH(consultatii.dataConsultatie) = :luna')
                ->andWhere('YEAR(consultatii.dataConsultatie) = :an');

            $params[':luna'] = intval($filter['luna']);
            $params[':an'] = $filter['an'];
        }

        if (isset($filter['medic'])) {
            $query->andWhere('medic.id = :medic');
            $params[':medic'] = $filter['medic'];
        }

        $query->setParameters($params);

        try {
            $sumaNumarLuna = $query->getQuery()->getScalarResult();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4001, $exception);
        }

        if (isset($filter['luna'])) {
            $sumaNumarLuna[0]['luna'] = $this->service->getLunileAnului()[intval($filter['luna'])];
        }

        $sumaNumarLuna[0]['valoare']  = $sumaNumarLuna[0]['valoare'] ?? 0;
        $sumaNumarLuna[0]['comision'] = $sumaNumarLuna[0]['comision'] ?? 0;

        return $sumaNumarLuna;
    }

    public function numarConsultatiiPeLuni($filter): array
    {
        $query = $this->createQueryBuilder('consultatii')
            ->select('COUNT (DISTINCT consultatii.id) AS totalConsultatiiLuna')
            ->innerJoin('consultatii.pret', 'pret')
            ->innerJoin('pret.medic', 'medic')
            ->where('consultatii.inchisa = :inchisa')
            ->andWhere('consultatii.stearsa = :stearsa')
            ->andWhere('consultatii.incasata = :incasata')
            ->andWhere('MONTH(consultatii.dataConsultatie) = :luna')
            ->andWhere('YEAR(consultatii.dataConsultatie) = :an');

        $params = [
            ':inchisa' => true,
            ':stearsa' => false,
            ':incasata' => true,
            ':luna' => intval($filter['luna']),
            ':an' => $filter['an'],
        ];

        if (isset($filter['medic'])) {
            $query->andWhere('medic.id = :medic');
            $params[':medic'] = $filter['medic'];
        }

        $query->setParameters($params);

        try {
            $consLuna = $query->getQuery()->getScalarResult();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4001, $exception);
        }

        $consLuna[0]['luna'] = $this->service->getLunileAnului()[intval($filter['luna'])];

        return null === $consLuna[0] ? 0 : $consLuna[0];
    }

    public function getNrPacientiConsultatiDeMedic($medicId): int
    {
        try {
            return $this->createQueryBuilder('consultatii')
                ->select('COUNT(DISTINCT pacient.id) AS totalPacientiMedic')
                ->innerJoin('consultatii.pacient', 'pacient')
                ->innerJoin('consultatii.pret', 'pret')
                ->innerJoin('pret.medic', 'medic')
                ->where('medic.id = :medicId')
                ->setParameter(':medicId', $medicId)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4041, $exception);
        }
    }

    public function getNrServiciiPrestateMedic($medicId): int
    {
        try {
            return $this->createQueryBuilder('consultatii')
                ->select('COUNT(DISTINCT consultatii.id) AS total')
                ->innerJoin('consultatii.pret', 'pret')
                ->innerJoin('pret.medic', 'medic')
                ->where('medic.id = :medicId')
                ->setParameter(':medicId', $medicId)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4041, $exception);
        }
    }

    public function inchideToateConsInvPacient($pacientId): bool
    {
        $query = $this->createQueryBuilder('consultatii')
            ->update(Consultatie::class, 'consultatii')
            ->set('consultatii.inchisa', ':inchisa')
            ->where('consultatii.pacient = :pacient')
            ->setParameters([
                ':inchisa' => true,
                ':pacient' => $pacientId,
            ]);

        try {
            $query->getQuery()->execute();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4001, $exception);
        }

        return true;
    }

    public function deleteConsultatie($id)
    {
        $consultatie = $this->em->getRepository(Consultatie::class)->find($id);

        $consultatie->setStearsa(true);

        try {
            $this->em->persist($consultatie);
            $this->em->flush();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 5001, $exception);
        }
    }

    public function ownerAreConsultatiiDeschise($ownerId): int
    {
        $query =  $this->createQueryBuilder('consultatii')
            ->select('COUNT(consultatii.id)')
            ->innerJoin('consultatii.pret', 'pret')
            ->innerJoin('pret.owner', 'owner')
            ->where('consultatii.inchisa = :inchisa')
            ->andWhere('owner.id = :ownerId')
            ->setParameters([
                ':inchisa' => false,
                ':ownerId' => $ownerId,
            ]);

        try {
            $areConsultatiiDeschise = $query->getQuery()->getSingleScalarResult();
        } catch (\Exception $exception) {
            throw new \Exception("Failed operation", 4041, $exception);
        }

        return $areConsultatiiDeschise > 0;
    }

    public function getConsultatiiNefacturatePacient($id): array
    {
        $query = $this->createQueryBuilder('consultatii');
        $query->select('DISTINCT consultatii.id',
                "DATE_FORMAT(consultatii.dataConsultatie, '%d-%m-%Y') AS dataConsultatie", 'consultatii.tarif',
                "CONCAT(medic.nume, ' ', medic.prenume) AS numeMedic", 'owner.denumire AS denumireOwner',
                "CONCAT(pacient.nume, ' ', pacient.prenume) AS numePacient", 'serviciu.denumire AS denumireServiciu',
                'owner.id AS ownerId')
            ->leftJoin('consultatii.pret', 'pret')
            ->leftJoin('pret.medic', 'medic')
            ->leftJoin('pret.serviciu', 'serviciu')
            ->leftJoin('pret.owner', 'owner')
            ->innerJoin('consultatii.pacient', 'pacient')
            ->where('consultatii.inchisa = :inchisa')
            ->andWhere('consultatii.stearsa = :stearsa')
            ->andWhere('consultatii.incasata = :incasata')
            ->andWhere('consultatii.facturata = :facturata')
            ->andWhere('consultatii.pacient = :pacientId')
            ->setParameters([
                ':inchisa' => true,
                ':stearsa' => false,
                ':incasata' => true,
                ':facturata' => false,
                ':pacientId' => $id,
            ])
            ->groupBy('consultatii.id');

        try {
            $consultatiiNefacturate = $query->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
        } catch (\Exception $exception) {
            throw new \Exception("Data collection error", 4041, $exception);
        }

        return $consultatiiNefacturate;
    }

    private function applyFilters($query, $filter): QueryBuilder
    {
        $parameters = [];
        $conditions = $query->expr()->andX();

        if (!empty($filter['value'])) {
            $orFilter = $query->expr()->orX(
                $query->expr()->like('medic.nume', ':filter'),
                $query->expr()->like('medic.prenume', ':filter'),
                $query->expr()->like('serviciu.denumire', ':filter'),
                $query->expr()->like('pacient.nume', ':filter'),
                $query->expr()->like('pacient.prenume', ':filter'),
                $query->expr()->like('pacient.cnp', ':filter'),
                $query->expr()->like('owner.denumire', ':filter')
            );

            $conditions->add($orFilter);

            $parameters[':filter'] = '%'.$filter['value'].'%';
        }

        foreach ($filter['propertyFilters'] as $entityFieldValue) {
            $entity = array_key_first($entityFieldValue);
            $field = array_key_first($entityFieldValue[$entity]);
            $value = $entityFieldValue[$entity][$field];

            $entityField = $entity.'.'.$field;
            $paramName = ':'.$entity.'_'.$field;
            $conditions->add($query->expr()->eq($entityField, $paramName));

            $parameters[$paramName] = $value;
        }

        if ($conditions->count() > 0) {
            $query->where($conditions);
        }

        if (!empty($parameters)) {
            $query->setParameters($parameters);
        }

        return $query;
    }

    private function buildSort($sort, $query): QueryBuilder
    {
        switch ($sort['column']) {
            case self::COL_NR_INREG:
                $query->addOrderBy('consultatii.nrInreg', $sort['dir']);
                break;
            case self::COL_DATA_PREZENTARII:
                $query->addOrderBy("DATE_FORMAT(consultatii.dataConsultatie, '%Y-%m-%d')", $sort['dir']);
                break;
            case self::COL_NUME_PACIENT:
                $query->addOrderBy("CONCAT(pacient.nume, ' ', pacient.prenume)", $sort['dir']);
                break;
            case self::COL_CNP_PACIENT:
                $query->addOrderBy('pacient.cnp', $sort['dir']);
                break;
            case self::COL_NUME_MEDIC:
                $query->addOrderBy("CONCAT(medic.nume, ' ', medic.prenume)", $sort['dir']);
                break;
            case self::COL_SERVICIU:
                $query->addOrderBy('consultatii.denumireServiciu', $sort['dir']);
                break;
            case self::COL_OWNER:
                $query->addOrderBy('owner.denumire', $sort['dir']);
                break;
            default:
                break;
        }
    }
}
