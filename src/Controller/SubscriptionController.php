<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionController extends AbstractController
{
    #[Route('/subscription/change', name: 'subscription_change')]
    public function changeSubscription(
        Request $request,
        SubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('L\'utilisateur doit être une instance de App\Entity\User');
        }

        $subscriptions = $subscriptionRepository->findAll();
        $currentSubscription = $user->getSubscription();

        // Traitement du formulaire
        if ($request->isMethod('POST')) {
            $subscriptionId = $request->request->get('subscription_id');
            if ($subscriptionId) {
                $subscription = $subscriptionRepository->find($subscriptionId);
                if ($subscription) {
                    $user->setSubscription($subscription);
                    $entityManager->flush();

                    $this->addFlash('success', 'Votre abonnement a été mis à jour avec succès.');
                    return $this->redirectToRoute('homepage');
                }
            }
        }

        return $this->render('subscription/change.html.twig', [
            'subscriptions' => $subscriptions,
            'currentSubscription' => $currentSubscription
        ]);
    }
}
