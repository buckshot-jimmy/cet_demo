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
use App\Services\FilterBuilder;
use App\Services\EntityHelper;
use App\Services\ResponseHelper;
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

    const VIEW = 'VIEW';
    const ADD_EDIT = 'ADD_EDIT';
    const DELETE = 'DELETE';

    public function __construct(
        private ConsultatieService            $consultationService,
        private AuthorizationCheckerInterface $authorizationChecker,
        private ResponseHelper                $responseHelper,
        private EntityHelper                  $entityHelper,
        private FilterBuilder                 $filterBuilder,
    ) {}

    #[Route("/consultatii", name: "consultatii", methods: ["GET"])]

    #[IsGratned('VIEW', subject: ....)] // THIS DOES NOT WORK SINCE THERE IS NO CONSULTATION PARAMETER IN THE CONTROLLER ACTION !!! SAME FOR THE OTHER METHODS BECAUSE I SEND THE ID VIA AJAX CALL, NOT AS URL PARAMETER
    #[IsGratned('VIEW', subject: ....)] // THIS DOES NOT WORK SINCE THERE IS NO CONSULTATION PARAMETER IN THE CONTROLLER ACTION !!! SAME FOR THE OTHER METHODS BECAUSE I SEND THE ID VIA AJAX CALL, NOT AS URL PARAMETER
    #[IsGratned('VIEW', subject: ....)] // THIS DOES NOT WORK SINCE THERE IS NO CONSULTATION PARAMETER IN THE CONTROLLER ACTION !!! SAME FOR THE OTHER METHODS BECAUSE I SEND THE ID VIA AJAX CALL, NOT AS URL PARAMETER
    
    public function consultatii()
    {
        if (!$this->authorizationChecker->isGranted(self::VIEW, new Consultatie())) {
            throw new AccessDeniedException();
        }

        return $this->render('@templates/consultatii.html.twig');
    }

    #[Route("/list_consultatii_curente_cabinet", name: "list_consultatii_curente_cabinet", methods: ["GET"])]
    public function listConsultatiiCurenteCabinet(Request $request) : JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(self::VIEW, new Consultatie())) {
            throw new AccessDeniedException();
        }

        $filter = $this->filterBuilder->buildFilter(
            $request,
            [
                0 => ['consultatii' => ['incasata' => false]],
                1 => ['consultatii' => ['stearsa' => false]]
            ]);

        $loggedUser = $this->getUser();

        if (in_array($loggedUser->getRole()->getDenumire(), [self::ROL_MEDIC, self::ROL_PSIHOLOG])) {
            $filter['propertyFilters'][] = ['pret' => ['medic' => $loggedUser->getId()]];
        }
        
        $consultatii = $this->consultationService->getAllConsultationsByFilter($filter);

        return $this->responseHelper->success([
            'data' => $consultatii['consultatii'],
            'recordsTotal' => intval($consultatii['total']),
            'recordsFiltered' => intval($consultatii['total'])
        ]);
    }

    #[Route("/consultatii_curente_cabinet", name: "consultatii_curente_cabinet", methods: ["GET"])]
    public function cabinet()
    {
        if (!$this->authorizationChecker->isGranted(self::VIEW, new Consultatie())) {
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
        if (!$this->authorizationChecker->isGranted(self::DELETE, new Consultatie())) {
            throw new AccessDeniedException();
        }

        $this->consultationService->deleteConsultation($request->request->get('id'));

        return $this->responseHelper->success();
    }

    #[Route("/get_consultatie_investigatie_eval", name: "get_consultatie_investigatie_eval", methods: ["GET"])]
    public function getConsultatieInvestigatieEvaluare(Request $request) : Response
    {
        $data = $this->consultationService->getConsultationInvestigationEvaluation($request->query->get('id'));

        $services = $this->entityHelper->getAllServices();
        $doctors = $this->entityHelper->getAllDoctors();
        $owners = $this->entityHelper->getAllOwners();
        $sendingDoctors = $this->entityHelper->getAllSendingDoctors();

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
            'servicii' => $services,
            'medici' => $doctors,
            'owners' => $owners['owners'],
            'mediciTrimitatori' => $sendingDoctors,
        ]);
    }

    #[Route("/edit_consultatie", name: "edit_consultatie", methods: ["POST"])]
    public function salveazaConsultatie(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'form')] ConsultatieDTO $dto
    ) : JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(
            self::ADD_EDIT,
            $this->consultationService->findOneConsultationById($request->request->get('id')))) {
            throw new AccessDeniedException();
        }

        $this->consultationService->saveConsultation($dto);

        return $this->responseHelper->success();
    }

    #[Route("/edit_investigatie", name: "edit_investigatie", methods: ["POST"])]
    public function salveazaInvestigatie(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'form')] ConsultatieDTO $dto
    ) : JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(
            self::ADD_EDIT,
            $this->consultationService->findOneConsultationById($request->request->get('id')))) {
            throw new AccessDeniedException();
        }

        $this->consultationService->saveInvestigation($dto);

        return $this->responseHelper->success();
    }

    #[Route("/edit_eval_psiho", name: "edit_eval_psiho", methods: ["POST"])]
    public function salveazaEvaluarePsihologica(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'form')] ConsultatieDTO $dto
    ) : JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(
            self::ADD_EDIT,
            $this->consultationService->findOneConsultationById($request->request->get('id')))) {
            throw new AccessDeniedException();
        }

        $this->consultationService->savePsihoEvaluation($dto);

        return $this->responseHelper->success();
    }

    #[Route("/get_istoric_pacient", name: "get_istoric_pacient", methods: ["POST"])]
    public function getIstoricPacient(Request $request) : JsonResponse
    {
        $patientId = $request->query->get('pacient_id');
        $serviceType = $request->query->get('tip_serviciu');

        $history = $this->consultationService->getPatientHistory($patientId, $serviceType);

        return $this->responseHelper->success(['istoric' => $history]);
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

        return $this->responseHelper->success();
    }

    #[Route("/inchide_deschide", name: "inchide_deschide", methods: ["POST"])]
    public function inchideDeschide(Request $request) : JsonResponse
    {
        $id = $request->request->get('id');

        if (!$this->authorizationChecker->isGranted(
            self::ADD_EDIT,
            $this->consultationService->findOneConsultationById($id))) {
            throw new AccessDeniedException();
        }

        $this->consultationService->openCloseConsultation($id);

        return $this->responseHelper->success();
    }

    #[Route("get_consultatii_luni", name: "get_consultatii_luni", methods: ["GET"])]
    public function getConsultatiiPeLuni(): JsonResponse
    {
        $calculation = $this->consultationService->calculateRevenueByMonth($this->getUser());

        return $this->responseHelper->success([
            'consultatii' => $calculation['consultatii'],
            'consultatiiMedic' => $calculation['consultatiiMedic'],
            'medic' => $this->getUser()->getNume() . " " . $this->getUser()->getPrenume()
        ]);
    }

    #[Route("get_incasari_medici_luni", name: "get_incasari_medici_luni", methods: ["GET"])]
    public function getIncasariMedicPeLuni(): JsonResponse
    {
        $doctorRevenue = $this->consultationService->calculateRevenueDoctorByMonth($this->getUser()->getId());

        return $this->responseHelper->success([
            'incasariMedic' => $doctorRevenue,
            'medic' => $this->getUser()->getNume() . " " . $this->getUser()->getPrenume()
        ]);
    }
}
