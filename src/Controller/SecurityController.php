<?php

namespace App\Controller;

use App\Entity\MesajAdmin;
use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Services\AdminService;
use App\Services\EmailService;
use App\Services\NomenclatoareService;
use App\Services\AuthService;
use App\Validator\UserConstraints;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    private const RESET_PASSWORD_TOKEN_SESSION_KEY = 'reset-password-token';

    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private AdminService $adminService,
        private TranslatorInterface $translator,
        private UserConstraints $userConstraint,
        private ValidatorInterface $validator,
    ) {}

    #[Route('/', name: 'app_login')]
    #[Route('', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('@security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request)
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key 
            on your firewall.');
    }

    #[Route('/admin', name: 'admin')]
    public function dashboard(NomenclatoareService $service): Response
    {
        $nomenclatoareMed = $this->adminService->getNomenclatoareMedicale();
        $userData = $this->adminService->getLoggedUserData($this->getUser());
        $valoriServicii = $this->adminService->getValoriGrafice($userData);
        $totaluriPacienti = $this->adminService->getTotaluriPacienti($userData);
        $mesaj = $this->em->getRepository(MesajAdmin::class)->getMesajAdmin();

        $this->adminService->setSessionInfo(
            $this->requestStack->getSession(),
            [
                'loggedUserData' => $userData,
                'roluri' => $nomenclatoareMed['roluri'],
                'specialitati' => $nomenclatoareMed['specialitati'],
                'titulaturi' => $nomenclatoareMed['titulaturi'],
                'informare' => $mesaj
            ]
        );

        return $this->render('@templates/main/index.html.twig', [
            'valoriCabinet' => $totaluriPacienti['valoriCabinet'],
            'consultatiiPacientiMedic' => $totaluriPacienti['consultatiiPacientiMedic'],
            'valoriMedic' => $valoriServicii,
            'luna' => $service->getLunileAnului()[intval(date('m'))],
        ]);
    }

    #[Route('/salveaza_informare', name: 'salveaza_informare', methods: ['POST'])]
    public function salveazaInformare(Request $request): Response
    {
        $mesaj = $request->request->get('mesaj');
        $activ = $request->request->get('activ');

        $this->em->getRepository(MesajAdmin::class)->saveMesaj($mesaj, $activ);

        $this->adminService->setSessionInfo(
            $this->requestStack->getSession(),
            [
                'informare' => $mesaj
            ]
        );

        return new JsonResponse([
            'status' => Response::HTTP_OK,
            'message' => $this->translator->trans('Successful operation'),
        ]);
    }

    #[Route('/get_informare', name: 'get_informare', methods: ['GET'])]
    public function getInformare(): Response
    {
        $mesajInformare = $this->em->getRepository(MesajAdmin::class)->getMesajAdmin();

        return new JsonResponse([
            'status' => Response::HTTP_OK,
            'message' => $this->translator->trans('Successful operation'),
            'activ' => true,
            'mesaj' => $mesajInformare,
        ]);
    }

    #[Route('/salveaza_noua_parola', name: 'salveaza_noua_parola', methods: ['POST'])]
    public function salveazaNouaParola(Request $request): Response
    {
        $parola = $request->request->get('parola');
        $parolaConfirmata = $request->request->get('parolaConfirmata');

        if ($parola !== $parolaConfirmata) {
            throw new \Exception('Passwords are not equal', 4009);
        }

        $errors = $this->validator->validate(
            [
                'loggedUserId' => $this->getUser()->getId(),
                'editUserId' => $this->getUser()->getId(),
                'role' => $this->getUser()->getRole()->getDenumire(),
                'edit_profile_password' => $parolaConfirmata,
            ],
            $this->userConstraint
        );

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

        $this->em->getRepository(User::class)->saveFirstTimeNewPassword([
            'password' => $parola,
            'passConf' => $parolaConfirmata,
            'user_id' => $this->getUser()->getId(),
        ]);

        return new JsonResponse([
            'status' => Response::HTTP_OK,
            'message' => $this->translator->trans('Password changed'),
        ]);
    }

    #[Route('/security/forgot_password_email', name: 'forgot_password_email', methods: ['GET', 'POST'])]
    public function forgotPasswordEmail(
        Request $request,
        EmailService $emailService,
        AuthService $authService,
    ): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $this->addFlash('danger', $this->translator->trans($form->getErrors(true)[0]->getMessage()));

                return $this->redirectToRoute('forgot_password_email');
            }

            $user = $this->em->getRepository(User::class)
                ->findOneBy(['email' => $form->get('email_forgot_password')->getData()]);

            if (!$user) {
                $this->addFlash('danger', $this->translator->trans('User not found'));

                return $this->redirectToRoute('forgot_password_email');
            }

            $formData = $request->request->all('reset_password_request_form');
            if (isset($formData['retrimite_email_btn'])) {
                $authService->removeResetToken($formData['email_forgot_password']);
            }

            $resetToken = $authService->generateResetToken($user);

            if (!$resetToken) {
                $this->addFlash('danger', $this->translator->trans('You have already requested a password reset'));

                return $this->redirectToRoute('app_login');
            }

            $emailData = [
                'recipient' => $user->getEmail(),
                'subject' => $this->translator->trans('Reset password'),
                'template' => 'emails/forgot_password_email.html.twig',
                'token' => $resetToken->getToken()
            ];

            $result = $emailService->sendEmail($emailData);

            $this->addFlash('success', $this->translator->trans($result['message']));

            return $this->redirectToRoute('app_login');
        }

        return $this->render('request/forgot_password_enter_email.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/security/forgot_password_reset', name: 'forgot_password_reset')]
    public function forgotPasswordReset(
        Request $request,
        AuthService $authService,
    ): Response
    {
        $session = $request->getSession();

        $token = $request->query->get('token');
        if ($token) {
            $session->set(self::RESET_PASSWORD_TOKEN_SESSION_KEY, $token);

            return $this->redirectToRoute('forgot_password_reset');
        }

        $token = $session->get(self::RESET_PASSWORD_TOKEN_SESSION_KEY);
        if (!$token) {
            $this->addFlash('danger', $this->translator->trans('Invalid reset link'));

            return $this->redirectToRoute('forgot_password_email');
        }

        $user = $authService->validateResetTokenAndGetUser($token);

        if (!$user) {
            $this->addFlash('danger', $this->translator->trans('Invalid reset link'));
            $session->remove(self::RESET_PASSWORD_TOKEN_SESSION_KEY);

            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $password = $form->get('new_password')->getData();
            $confirmNewPassword = $form->get('confirm_new_password')->getData();

            if ($password !== $confirmNewPassword) {
                $this->addFlash('danger', $this->translator->trans('Passwords are not equal'));

                return $this->render('reset_password/forgot_password_reset.html.twig', [
                    'resetForm' => $form->createView(),
                ]);
            }

            $errors = $this->validator->validate(
                [
                    'loggedUserId' => $user->getId(),
                    'editUserId' => $user->getId(),
                    'role' => $user->getRole()->getDenumire(),
                    'password' => $password,
                ],
                $this->userConstraint
            );

            if (count($errors)) {
                $messages = $this->adminService->buildValidationErrors($errors);

                $this->addFlash('danger', $messages);

                return $this->render('reset_password/forgot_password_reset.html.twig', [
                    'resetForm' => $form->createView(),
                ]);
            }

            $authService->processRequestReset($session, $user, $password, $token);

            $this->addFlash('success', $this->translator->trans('Password changed'));
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/forgot_password_reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    #[Route('/2fa/resend', name: '2fa_resend', methods: ['POST'])]
    public function resendTwoFactorCode(Request $request, AuthService $authService): Response
    {
        if (!$this->isCsrfTokenValid('2fa_resend', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('Invalid 2FA request'));

            return $this->redirectToRoute('2fa_login');
        }

        $user = $this->getUser();
        if (!$user instanceof TwoFactorInterface) {
            $this->addFlash('danger',
                $this->translator->trans('2FA code is invalid. Please login again or request a new code.'));

            return $this->redirectToRoute('app_login');
        }

        if (!$authService->resendTwoFactorCode($user)) {
            $this->addFlash('danger',
                $this->translator->trans('Could not send a new 2FA token. Please try again'));

            return $this->redirectToRoute('2fa_login');
        }

        $this->addFlash('success', $this->translator->trans('A new 2FA token was sent to your email'));

        return $this->redirectToRoute('2fa_login');
    }

    #[Route('/2fa/cancel', name: 'app_2fa_cancel', methods: ['POST'])]
    public function cancelTwoFactor(Request $request, TokenStorageInterface $tokenStorage): Response
    {
        if (!$this->isCsrfTokenValid('2fa_cancel', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('2fa_login');
        }

        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        $tokenStorage->setToken(null);

        return $this->redirectToRoute('app_login');
    }
}
