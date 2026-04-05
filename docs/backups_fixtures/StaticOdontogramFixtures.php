<?php

namespace App\DataFixtures;

use App\Entity\Appointment;
use App\Entity\Odontogram;
use App\Entity\OdontogramDetail;
use App\Entity\Pathology;
use App\Entity\PathologyType;
use App\Entity\ToothFace;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class StaticOdontogramFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Usamos cita real
        $appointment = $manager->getRepository(Appointment::class)->findOneBy([]);
        
        if (!$appointment) return;

        $treatment = $appointment->getTreatment();
        $pathologyType = $manager->getRepository(PathologyType::class)->findOneBy(['name' => 'Caries']);

        // Datos estáticos con COINCIDENCIAS
        // El diente 16 tendrá 3 caras afectadas por la misma patología
        $casosDePrueba = [
            ['diente' => 16, 'caras' => ['O', 'M', 'D'], 'color' => '#FF0000'], // Molar con 3 caras
            ['diente' => 11, 'caras' => ['V'], 'color' => '#00FF00'],           // Incisivo 1 cara
            ['diente' => 55, 'caras' => ['O', 'L'], 'color' => '#FF0000'],      // Temporal con 2 caras
        ];

        foreach ($casosDePrueba as $caso) {
            $detail = new OdontogramDetail();
            $detail->setToothNumber($caso['diente']);
            
            // Buscamos o creamos el Odontograma para esta cita
            $odontogram = $manager->getRepository(Odontogram::class)->findOneBy(['visit' => $appointment]);
            $detail->setOdontogram($odontogram);

            // Creamos la patología
            $path = new Pathology();
            $path->setDescription("Prueba estática diente " . $caso['diente']);
            $path->setProtocolColor($caso['color']);
            $path->setPathologyType($pathologyType);
            $path->setTreatment($treatment);
            $manager->persist($path);

            $detail->setPathology($path);
            $manager->persist($detail);

            // AHORA CREAMOS LAS COINCIDENCIAS DE CARAS
            foreach ($caso['caras'] as $faceName) {
                $face = new ToothFace();
                $face->setFaceName($faceName);
                $face->setOdontogramDetail($detail);
                $manager->persist($face);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AppFixtures::class,
        ];
    }
}