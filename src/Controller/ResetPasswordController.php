<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordRequestType;
use App\Form\ResetPasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private TokenGeneratorInterface $tokenGenerator,
        private \Symfony\Component\HttpFoundation\RequestStack $requestStack
    ) {
    }

    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request): Response
    {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processSendingPasswordResetEmail(
                $form->get('email')->getData()
            );
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Generate a fake token if the user does not exist or someone hit this page directly.
        // This prevents exposing whether or not a user was found with the given email address or not
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = 'fake-token';
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, string $token = null): Response
    {
        if ($token) {
            // Store the token in session and remove it from the URL, to avoid the URL being
            // loaded in a browser and potentially leaking the token to JavaScript or other JS.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'resetToken' => $token,
        ]);

        if (!$user || !$user->isResetTokenValid()) {
            $this->removeTokenFromSession();

            return $this->render('reset_password/expired.html.twig');
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            // Encode the plain password, and set it.
            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->entityManager->flush();

            // The session is cleaned up after the password has been changed.
            $this->removeTokenFromSession();

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData): Response
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        // Generate a unique reset token
        $resetToken = $this->tokenGenerator->generateToken();

        $user->setResetToken($resetToken);
        // Set expiration time to 1 hour from now
        $user->setResetTokenExpiresAt(new \DateTime('+1 hour'));

        $this->entityManager->flush();

        // Store the token in session for the route that will check if the email was really sent
        $this->setTokenObjectInSession($resetToken);

        $email = (new TemplatedEmail())
            ->from('noreply@pdfpro.com')
            ->to($user->getEmail())
            ->subject('Votre demande de réinitialisation de mot de passe')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'tokenLifetime' => 3600, // 1 hour in seconds
                'resetUrl' => $this->generateUrl(
                    'app_reset_password',
                    ['token' => $resetToken],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'user' => $user
            ]);

        $this->mailer->send($email);

        return $this->redirectToRoute('app_check_email');
    }

    private function setTokenObjectInSession(string $resetToken): void
    {
        $this->getSession()->set('ResetPasswordToken', $resetToken);
    }

    private function getTokenObjectFromSession(): ?string
    {
        return $this->getSession()->get('ResetPasswordToken');
    }

    private function storeTokenInSession(string $token): void
    {
        $this->getSession()->set('ResetPasswordToken', $token);
    }

    private function getTokenFromSession(): ?string
    {
        return $this->getSession()->get('ResetPasswordToken');
    }

    private function removeTokenFromSession(): void
    {
        $this->getSession()->remove('ResetPasswordToken');
    }

    private function getSession()
    {
        return $this->requestStack->getSession();
    }
}
