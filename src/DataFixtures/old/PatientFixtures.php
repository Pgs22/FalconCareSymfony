<?php

namespace App\DataFixtures;

use App\Entity\Patient;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class PatientFixtures extends Fixture
{
    public const COUNT = 20;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        for ($i = 0; $i < self::COUNT; $i++) {
            $patient = new Patient();

            $patient->setIdentityDocument($faker->unique()->bothify('########A'));
            $patient->setFirstName($faker->firstName());
            $patient->setLastName($faker->lastName());
            $patient->setSsNumber($faker->optional()->bothify('SS-###'));
            $patient->setPhone($faker->phoneNumber());
            $patient->setEmail($faker->unique()->safeEmail());
            $patient->setAddress($faker->address());
            $patient->setConsultationReason($faker->sentence(6));
            $patient->setFamilyHistory($faker->sentence(8));
            $patient->setHealthStatus($faker->randomElement(['Good', 'Regular', 'Critical']));
            $patient->setLifestyleHabits($faker->sentence(8));
            $patient->setRegistrationDate(new \DateTimeImmutable());
            $patient->setMedicationAllergies($faker->sentence(6));

            $manager->persist($patient);
            $this->addReference('patient_' . $i, $patient);
        }

        $manager->flush();
    }
}