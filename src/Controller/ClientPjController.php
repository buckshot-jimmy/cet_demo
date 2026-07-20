<?php

namespace App\Controller;

use App\Entity\PersoanaJuridica;
use App\Services\AdminService;
use App\Validator\FirmeConstraints;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ClientPjController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private FirmeConstraints $constraint,
        private TranslatorInterface $translator,
        private AdminService $adminService,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    #[Route('/clienti_pj', name: 'clienti_pj', methods: ['GET'])]
    public function clientiPj(): Response
    {
        if (!$this->authorizationChecker->isGranted('VIEW', new PersoanaJuridica())) {
            throw new AccessDeniedException();
        }

        return $this->render('@templates/persoane_juridice.html.twig');
    }

    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $filter = array_merge(
            $request->query->all('search'),
            [
                'sort' => $request->query->all('order')[0] ?? null,
                'start' => $request->query->get('start'),
                'length' => $request->query->get('length'),
            ]
        );

        $pj = $this->em->getRepository(PersoanaJuridica::class)->getAllClientiPj($filter);

        return new JsonResponse([
            'data' => $pj['clienti_pj'],
            'recordsTotal' => intval($pj['total']),
            'recordsFiltered' => intval($pj['total']),
        ]);
    }

    #[Route('add_edit_pj', name: 'add_edit_pj', methods: ['POST'])]
    public function salveazaClientPj(Request $request): Response
    {
        if (!$this->authorizationChecker->isGranted('ADD_EDIT', new PersoanaJuridica())) {
            throw new AccessDeniedException();
        }

        parse_str($request->request->get('form'), $formData);

        $errors = $this->validator->validate($formData, $this->constraint);

        if (count($errors)) {
            $messages = $this->adminService->buildValidationErrors($errors);

            return new JsonResponse(
                [
                    'status' => Response::HTTP_BAD_REQUEST,
                    'message' => $this->translator->trans('Failed operation').' '.$messages,
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->em->getRepository(PersoanaJuridica::class)->saveClientPj($formData);

        return new JsonResponse(
            [
                'status' => Response::HTTP_OK,
                'message' => $this->translator->trans('Successful operation'),
            ]
        );
    }

    #[Route('get_client_pj', name: 'get_client_pj', methods: ['GET'])]
    public function getClientPj(Request $request): Response
    {
        if (!$this->authorizationChecker->isGranted('VIEW', new PersoanaJuridica())) {
            throw new AccessDeniedException();
        }

        $data = $this->em->getRepository(PersoanaJuridica::class)->getClientPj($request->query->get('id'));

        return $this->render('@templates/firme/pj_content.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('get_clienti_pj_by_cui', name: 'get_clienti_pj_by_cui', methods: ['GET'])]
    public function getClientiPjByCui(Request $request): Response
    {
        $cuiRequest = $request->query->get('cui');

        $clienti = $this->em->getRepository(PersoanaJuridica::class)->getClientiByCui($cuiRequest);

        return new JsonResponse([
            'clienti' => $clienti,
            'status' => Response::HTTP_OK,
            'message' => $this->translator->trans("Successful operation")
        ]);
    }

    #[Route('/sterge_client_pj', name: 'sterge_client_pj', methods: ['POST'])]
    public function sterge(Request $request): Response
    {
        if (!$this->authorizationChecker->isGranted('DELETE', new PersoanaJuridica())) {
            throw new AccessDeniedException();
        }

        $this->em->getRepository(PersoanaJuridica::class)->deleteClientPj($request->request->get('id'));

        return new JsonResponse([
            'status' => Response::HTTP_OK,
            'message' => $this->translator->trans('Successful operation'),
        ]);
    }
}
