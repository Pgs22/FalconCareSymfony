<?php

namespace App\DataFixtures;

use App\Entity\Doctor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class DoctorFixtures extends Fixture
{
    public const COUNT = 10;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $specialties = [
            'Orthodontics',
            'Endodontics',
            'Periodontics',
            'Prosthodontics',
            'Oral Surgery',
            'Pediatric Dentistry',
            'General Dentistry'
        ];

        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        for ($i = 0; $i < self::COUNT; $i++) {
            $doctor = new Doctor();

            $doctor->setFirstName($faker->firstName());
            $doctor->setLastNames($faker->lastName() . ' ' . $faker->lastName());
            $doctor->setSpecialty($faker->randomElement($specialties));
            $doctor->setAssignedWeekday($faker->optional(0.7)->randomElement($weekdays));
            $doctor->setPhone($faker->phoneNumber());
            $doctor->setEmail($faker->unique()->safeEmail());

            $manager->persist($doctor);
            $this->addReference('doctor_' . $i, $doctor);
        }

        $manager->flush();
    }
}