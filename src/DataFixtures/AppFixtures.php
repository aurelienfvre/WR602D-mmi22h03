<?php

namespace App\DataFixtures;

use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\File;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Création des abonnements
        $free = new Subscription();
        $free->setName('Gratuit');
        $free->setDescription('Idéal pour découvrir notre service');
        $free->setMaxPdf(5);
        $free->setPrice(0);
        $manager->persist($free);
        
        $standard = new Subscription();
        $standard->setName('Standard');
        $standard->setDescription('Pour les utilisateurs réguliers');
        $standard->setMaxPdf(30);
        $standard->setPrice(9.99);
        $standard->setSpecialPrice(7.99);
        $standard->setSpecialPriceFrom(new \DateTime('2025-01-01'));
        $standard->setSpecialPriceTo(new \DateTime('2025-04-30'));
        $manager->persist($standard);
        
        $pro = new Subscription();
        $pro->setName('Professionnel');
        $pro->setDescription('Pour les besoins intensifs');
        $pro->setMaxPdf(100);
        $pro->setPrice(19.99);
        $manager->persist($pro);

        // Création des utilisateurs
        $admin = new User();
        $admin->setEmail('admin@pdf-generator.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin'));
        $admin->setFirstname('Admin');
        $admin->setLastname('System');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setSubscription($pro);
        $manager->persist($admin);
        
        $user = new User();
        $user->setEmail('user@pdf-generator.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $user->setFirstname('Jean');
        $user->setLastname('Dupont');
        $user->setRoles(['ROLE_USER']);
        $user->setSubscription($standard);
        $manager->persist($user);
        
        // Création de quelques fichiers d'exemple
        $file1 = new File();
        $file1->setName('rapport-2025.pdf');
        $file1->setCreatedAt(new \DateTimeImmutable('2025-03-01'));
        $file1->setUser($user);
        $manager->persist($file1);
        
        $file2 = new File();
        $file2->setName('facture-mars.pdf');
        $file2->setCreatedAt(new \DateTimeImmutable('2025-03-05'));
        $file2->setUser($user);
        $manager->persist($file2);
        
        $file3 = new File();
        $file3->setName('presentation.pdf');
        $file3->setCreatedAt(new \DateTimeImmutable('2025-03-10'));
        $file3->setUser($admin);
        $manager->persist($file3);

        $manager->flush();
    }
}