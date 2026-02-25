<?php

namespace App\DataFixtures;

use App\Entity\Appointment;
use App\Entity\Odontogram;
use App\Entity\Pathology;
use App\Entity\Tooth;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class OdontogramFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 120;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $surfaces = ['occlusal', 'mesial', 'distal', 'buccal', 'lingual'];
        $statuses = ['healthy', 'caries', 'treated', 'extracted'];

        for ($i = 0; $i < self::COUNT; $i++) {
            $odontogram = new Odontogram();

            $odontogram->setToothSurface($faker->optional()->randomElement($surfaces));
            $odontogram->setStatus($faker->randomElement($statuses));

            $appointmentIndex = $faker->numberBetween(0, AppointmentFixtures::COUNT - 1);
            $toothIndex = $faker->numberBetween(0, ToothFixtures::COUNT - 1);
            $pathologyIndex = $faker->numberBetween(0, PathologyFixtures::COUNT - 1);

            $odontogram->setVisit($this->getReference('appointment_' . $appointmentIndex, Appointment::class));
            $odontogram->setTooth($this->getReference('tooth_' . $toothIndex, Tooth::class));
            $odontogram->setPathology($this->getReference('pathology_' . $pathologyIndex, Pathology::class));

            $manager->persist($odontogram);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AppointmentFixtures::class,
            ToothFixtures::class,
            PathologyFixtures::class,
        ];
    }
}