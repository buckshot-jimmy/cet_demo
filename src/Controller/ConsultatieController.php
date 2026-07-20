<?php


namespace App\Controller;


use App\DTO\ConsultatieDTO;
use App\Entity\Consultatie;
use App\Entity\MedicTrimitator;
use App\Entity\Owner;
use App\Entity\Serviciu;
use App\Entity\User;
use App\PDF\Service\PdfService;
use App\Services\ConsultatieService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConsultatieController extends AbstractController
{
    const ROL_MEDIC = 'ROLE_Medic';
    const ROL_PSIHOLOG = 'ROLE_Psiholog';

    public function __construct(
        private EntityManagerInterface        $em,
        private TranslatorInterface           $translator,
        private ConsultatieService            $consultatiiService,
        private AuthorizationCheckerInterface $authorizationChecker
    ) {}

    #[Route("/consultatii", name: "consultatii", methods: ["GET"])]
    public function consultatii()
    {
        if (!$this->authorizationChecker->isGranted('VIEW', new Consultatie())) {
            throw new AccessDeniedException();
        }

        return $this->render('@templates/consultatii.html.twig');
    }

    #[Route("/list_consultatii_curente_cabinet", name: "list_consultatii_curente_cabinet", methods: ["GET"])]
    public function listConsultatiiCurenteCabinet(Request $request) : JsonResponse
    {
        if (!$this->authorizationChecker->isGranted('VIEW', new Consultatie())) {
            throw new AccessDeniedException();
        }

        $filter = array_merge(
            $request->query->all('search'),
            [
                'sort' => ($request->query->all('order'))[0] ?? null,
                'start' => $request->query->get('start'),
                'length' => $request->query->get('length')
            ],
            ['propertyFilters' => [
                0 => ['consultatii' => ['incasata' => false]],
                1 => ['consultatii' => ['stearsa' => false]]]
            ]
        );

        $loggedUser = $this->getUser();

        if (in_array($loggedUser->getRole()->getDenumire(), [self::ROL_MEDIC, self::ROL_PSIHOLOG])) {
            $filter['propertyFilters'][] = ['pret' => ['medic' => $loggedUser->getId()]];
        }

        $consultatii = $this->em->getRepository(Consultatie::class)->getAllConsultatiiByFilter($filter);

        return new JsonResponse([
            'data' => $consultatii['consultatii'],
            'recordsTotal' => intval($consultatii['total']),
            'recordsFiltered' => intval($consultatii['total'])
        ]);
    }

    #[Route("/consultatii_curente_cabinet", name: "consultatii_curente_cabinet", methods: ["GET"])]
    public function cabinet()
    {
        if (!$this->authorizationChecker->isGranted('VIEW', new Consultatie())) {
            throw new AccessDeniedException();
        }

        $medicLogat = $this->getUser();

        return $this->render('@templates/cabinet.html.twig', [
            'medicLogat' =>  in_array($medicLogat->getRole()->getDenumire(), [self::ROL_MEDIC, self::ROL_PSIHOLOG])
                ? " - " . $medicLogat->getNume() . " " . $medicLogat->getPrenume()
                : ""
        ]);
    }

    #[Route("/sterge_consultatie", name: "sterge_consultatie", methods: ["POST"])]
    public function stergeConsultatie(Request $request) : JsonResponse
    {
        if (!$this->authorizationChecker->isGranted('DELETE', new Consultatie())) {
            throw new AccessDeniedException();
        }

        $this->em->getRepository(Consultatie::class)->deleteConsultatie($request->request->get('id'));

        return new JsonResponse([
            "status" => Response::HTTP_OK,
            "message" => $this->translator->trans("Successful operation")
        ], Response::HTTP_OK);
    }

    #[Route("/get_consultatie_investigatie_eval", name: "get_consultatie_investigatie_eval", methods: ["GET"])]
    public function getConsultatieInvestigatieEvaluare(Request $request) : Response
    {
        $data = $this->em->getRepository(Consultatie::class)
            ->getConsultatieInvestigatieEvaluare($request->query->get('id'));

        $servicii = $this->em->getRepository(Serviciu::class)->getAllServicii();
        $medici = $this->em->getRepository(User::class)->getAllMedici();
        $owners = $this->em->getRepository(Owner::class)->getAllOwners();
        $mediciTrimitatori = $this->em->getRepository(MedicTrimitator::class)->findAll();

        $template = match ($data['tipServiciu']) {
            0 => 'consultatie_content.html.twig',
            1 => 'investigatie_content.html.twig',
            2 => 'eval_psiho_content.html.twig',
        };

        if ($request->query->get('view')) {
            $template = 'view_' . $template;
        }

        return $this->render('@templates/consultatii/' . $template, [
            'data' => $data,
            'servicii' => $servicii,
            'medici' => $medici,
            'owners' => $owners['owners'],
            'mediciTrimitatori' => $mediciTrimitatori,
        ]);
    }

    #[Route("/edit_consultatie", name: "edit_consultatie", methods: ["POST"])]
    public function salveazaConsultatie(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'form')] ConsultatieDTO $dto
    ) : JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(
            'ADD_EDIT',
            $this->em->getRepository(Consultatie::class)->findOneBy(['id' => $request->request->get('id')]))) {
            throw new AccessDeniedException();
        }

        $this->em->getRepository(Consultatie::class)->saveConsultatie($dto);

        return new JsonResponse([
            "status" => Response::HTTP_OK,
            "message" => $this->translator->trans("Successful operation")
        ]);
    }

    #[Route("/edit_investigatie", name: "edit_investigatie", methods: ["POST"])]
    public function salveazaInvestigatie(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'form')] ConsultatieDTO $dto
    ) : JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(
            'ADD_EDIT',
            $this->em->getRepository(Consultatie::class)->findOneBy(['id' => $request->request->get('id')]))) {
            throw new AccessDeniedException();
        }

        $this->em->getRepository(Consultatie::class)->saveInvestigatie($dto);

        return new JsonResponse([
            "status" => Response::HTTP_OK,
            "message" => $this->translator->trans("Successful operation")
        ]);
    }

    #[Route("/edit_eval_psiho", name: "edit_eval_psiho", methods: ["POST"])]
    public function salveazaEvaluarePsihologica(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'form')] ConsultatieDTO $dto
    ) : JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(
            'ADD_EDIT',
            $this->em->getRepository(Consultatie::class)->findOneBy(['id' => $request->request->get('id')]))) {
            throw new AccessDeniedException();
        }

        $this->em->getRepository(Consultatie::class)->saveEvaluarePsihologica($dto);

        return new JsonResponse([
            "status" => Response::HTTP_OK,
            "message" => $this->translator->trans("Successful operation")
        ]);
    }

    #[Route("/get_istoric_pacient", name: "get_istoric_pacient", methods: ["POST"])]
    public function getIstoricPacient(Request $request) : JsonResponse
    {
        $pacientId = $request->query->get('pacient_id');
        $tipServiciu = $request->query->get('tip_serviciu');

        $istoric = $this->em->getRepository(Consultatie::class)->getIstoricPacient($pacientId, $tipServiciu);

        return new JsonResponse([
            "istoric" => $istoric,
            "status" => Response::HTTP_OK,
            "message" => $this->translator->trans("Successful operation")
        ]);
    }

    #[Route("/pdf_servicii_formulare", name: "pdf_servicii_formulare", methods: ["POST"])]
    public function pdfServiciiFormulare(Request $request, PdfService $pdfService): JsonResponse
    {
        $template = $request->request->get('template');

        $pdfService->printToPdf(
            $request->request->get('id'),
            $template,
            [
                'orientation' => $template === 'buletin_investigatie.html.twig'
                    ? 'L'
                    : 'P',
                'footer' => '{PAGENO}/{nb}'
            ]
        );

        return new JsonResponse([
            Response::HTTP_OK,
            $this->translator->trans("Successful operation")
        ]);
    }

    #[Route("/inchide_deschide", name: "inchide_deschide", methods: ["POST"])]
    public function inchideDeschide(Request $request) : JsonResponse
    {
        $id = $request->request->get('id');

        if (!$this->authorizationChecker->isGranted(
            'ADD_EDIT',
            $this->em->getRepository(Consultatie::class)->findOneBy(['id' => $id]))) {
            throw new AccessDeniedException();
        }

        $this->em->getRepository(Consultatie::class)->inchideDeschide($id);

        return new JsonResponse([
            "status" => Response::HTTP_OK,
            "message" => $this->translator->trans("Successful operation")
        ]);
    }

    #[Route("get_consultatii_luni", name: "get_consultatii_luni", methods: ["GET"])]
    public function getConsultatiiPeLuni(): JsonResponse
    {
        $calcule = $this->consultatiiService->calculeazaConsultatiiPeLuni($this->getUser());

        return new JsonResponse([
            "status" => Response::HTTP_OK,
            "message" => $this->translator->trans("Successful operation"),
            'consultatii' => $calcule['consultatii'],
            'consultatiiMedic' => $calcule['consultatiiMedic'],
            'medic' => $this->getUser()->getNume() . " " . $this->getUser()->getPrenume()
        ]);
    }

    #[Route("get_incasari_medici_luni", name: "get_incasari_medici_luni", methods: ["GET"])]
    public function getIncasariMedicPeLuni(): JsonResponse
    {
        $incasariMedic = $this->consultatiiService->calculeazaIncasariMedicPeLuni($this->getUser()->getId());

        return new JsonResponse([
            "status" => Response::HTTP_OK,
            "message" => $this->translator->trans("Successful operation"),
            'incasariMedic' => $incasariMedic,
            'medic' => $this->getUser()->getNume() . " " . $this->getUser()->getPrenume()
        ]);
    }
}
