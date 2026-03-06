<?php

namespace App\DataFixtures;

use App\Entity\Tooth;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ToothFixtures extends Fixture
{
    public const COUNT = 32;

    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            $tooth = new Tooth();

            $tooth->setToothId($i);
            $tooth->setDescription('Tooth ' . $i);

            $manager->persist($tooth);
            $this->addReference('tooth_' . ($i - 1), $tooth);
        }

        $manager->flush();
    }
}