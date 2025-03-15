<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\SubscriptionRepository;

class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(SubscriptionRepository $subscriptionRepository): Response
    {
        // Si l'utilisateur est connecté, on récupère ses données
        $user = $this->getUser();
        $subscription = null;

        if ($user instanceof User) {
            $subscription = $user->getSubscription();

            // Si l'utilisateur n'a pas d'abonnement, on lui propose les abonnements disponibles
            if (!$subscription) {
                $subscriptions = $subscriptionRepository->findAll();
                return $this->render('home/index.html.twig', [
                    'subscriptions' => $subscriptions
                ]);
            }
        }

        return $this->render('home/index.html.twig');
    }
}
