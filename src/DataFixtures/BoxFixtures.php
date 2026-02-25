<?php

namespace App\DataFixtures;

use App\Entity\Box;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class BoxFixtures extends Fixture
{
    public const COUNT = 8;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        for ($i = 0; $i < self::COUNT; $i++) {
            $box = new Box();

            $box->setBoxName('Box ' . ($i + 1));
            $box->setStatus($faker->boolean(80));
            $box->setCapacity($faker->numberBetween(1, 4));

            $manager->persist($box);
            $this->addReference('box_' . $i, $box);
        }

        $manager->flush();
    }
}