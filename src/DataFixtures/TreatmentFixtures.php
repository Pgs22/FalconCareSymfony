<?php

namespace App\DataFixtures;

use App\Entity\Treatment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class TreatmentFixtures extends Fixture
{
    public const COUNT = 12;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $treatments = [
            'Dental cleaning',
            'Cavity filling',
            'Root canal',
            'Tooth extraction',
            'Dental crown',
            'Dental implant',
            'Teeth whitening',
            'Orthodontic check',
            'Gum treatment',
            'X-ray review',
            'Sealant application',
            'Emergency visit'
        ];

        for ($i = 0; $i < self::COUNT; $i++) {
            $treatment = new Treatment();

            $treatment->setTreatmentName($treatments[$i]);
            $treatment->setDescription($faker->paragraph(2));
            $treatment->setEstimatedDuration($faker->numberBetween(15, 180));

            $manager->persist($treatment);
            $this->addReference('treatment_' . $i, $treatment);
        }

        $manager->flush();
    }
}