<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const COUNT = 15;

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $allowedRoles = ['ROLE_ADMIN', 'ROLE_DOCTOR', 'ROLE_STAFF'];

        for ($i = 0; $i < self::COUNT; $i++) {
            $user = new User();

            $user->setEmail($faker->unique()->safeEmail());

            $extraRoles = $faker->boolean(70) ? [$faker->randomElement($allowedRoles)] : [];
            $user->setRoles($extraRoles);

            $plainPassword = 'Password123!';
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            $manager->persist($user);
            $this->addReference('user_' . $i, $user);
        }

        $manager->flush();
    }
}