<?php

namespace App\DataFixtures;

use App\Entity\Pathology;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class PathologyFixtures extends Fixture
{
    public const COUNT = 8;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $pathologies = [
            'Dental caries',
            'Gingivitis',
            'Periodontitis',
            'Tooth fracture',
            'Pulpitis',
            'Abscess',
            'Enamel erosion',
            'Tooth mobility'
        ];

        foreach ($pathologies as $index => $description) {

            $pathology = new Pathology();

            $pathology->setDescription($description);

            // Color hexadecimal tipo #FF0000
            $pathology->setProtocolColor(
                sprintf('#%06X', mt_rand(0, 0xFFFFFF))
            );

            $manager->persist($pathology);
            $this->addReference('pathology_' . $index, $pathology);
        }

        $manager->flush();
    }
}