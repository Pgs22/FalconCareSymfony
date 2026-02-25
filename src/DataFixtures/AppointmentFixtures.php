<?php

namespace App\DataFixtures;

use App\Entity\Appointment;
use App\Entity\Box;
use App\Entity\Doctor;
use App\Entity\Patient;
use App\Entity\Treatment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppointmentFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 60;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $statuses = ['scheduled', 'completed', 'cancelled'];

        for ($i = 0; $i < self::COUNT; $i++) {
            $appointment = new Appointment();

            $date = $faker->dateTimeBetween('-30 days', '+30 days');
            $appointment->setVisitDate(\DateTime::createFromFormat('Y-m-d', $date->format('Y-m-d')));

            $time = $faker->dateTimeBetween('today 08:00', 'today 20:00');
            $appointment->setVisitTime(\DateTime::createFromFormat('H:i:s', $time->format('H:i:s')));

            $appointment->setConsultationReason($faker->sentence(6));
            $appointment->setObservations($faker->paragraph(2));
            $appointment->setStatus($faker->randomElement($statuses));

            $patientIndex = $faker->numberBetween(0, PatientFixtures::COUNT - 1);
            $doctorIndex = $faker->numberBetween(0, DoctorFixtures::COUNT - 1);
            $boxIndex = $faker->numberBetween(0, BoxFixtures::COUNT - 1);
            $treatmentIndex = $faker->numberBetween(0, TreatmentFixtures::COUNT - 1);

            $appointment->setPatient($this->getReference('patient_' . $patientIndex, Patient::class));
            $appointment->setDoctor($this->getReference('doctor_' . $doctorIndex, Doctor::class));
            $appointment->setBox($this->getReference('box_' . $boxIndex, Box::class));
            $appointment->setTreatment($this->getReference('treatment_' . $treatmentIndex, Treatment::class));

            $manager->persist($appointment);
            $this->addReference('appointment_' . $i, $appointment);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            PatientFixtures::class,
            DoctorFixtures::class,
            BoxFixtures::class,
            TreatmentFixtures::class,
        ];
    }
}