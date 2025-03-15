<?php

namespace App\Controller;

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

        $subscriptions = $subscriptionRepository->findAll();
        $currentSubscription = $this->getUser()->getSubscription();

        // Traitement du formulaire
        if ($request->isMethod('POST')) {
            $subscriptionId = $request->request->get('subscription_id');
            if ($subscriptionId) {
                $subscription = $subscriptionRepository->find($subscriptionId);
                if ($subscription) {
                    $user = $this->getUser();
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
