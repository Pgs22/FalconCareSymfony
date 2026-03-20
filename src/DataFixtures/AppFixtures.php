<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Patient;
use App\Entity\Appointment;
use App\Entity\Box;
use App\Entity\Doctor;
use App\Entity\Odontogram;
use App\Entity\OdontogramaDetail;
use App\Entity\Pathology;
use App\Entity\PathologyType;
use App\Entity\ToothFace;
use App\Entity\Treatment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('es_ES');

        $boxes = [];
        for ($i = 1; $i <= 3; $i++) {
            $box = new Box();
            $box->setBoxName("Box $i");
            $box->setStatus(true);
            $box->setCapacity(1);
            $manager->persist($box);
            $boxes[] = $box;
        }

        $doctors = [];
        for ($i = 0; $i < 3; $i++) {
            $dr = new Doctor();
            $dr->setFirstName($faker->firstName);
            $dr->setLastNames($faker->lastName . " " . $faker->lastName);
            $dr->setSpecialty("Odontología General");
            $dr->setEmail($faker->email);
            $dr->setPhone($faker->phoneNumber);
            $manager->persist($dr);
            $doctors[] = $dr;
        }

        $pathologyTypes = [];
        $catalogo = [['Caries', 30], ['Limpieza', 30], ['Endodoncia', 60]];
        foreach ($catalogo as [$nombre, $duracion]) {
            $type = new PathologyType();
            $type->setName($nombre);
            $type->setDefaultDuration($duracion);
            $manager->persist($type);
            $pathologyTypes[] = $type;
        }

        $admin = new User();
        $admin->setEmail('admin@falconcare.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // PATIENTS
        for ($i = 0; $i < 10; $i++) {
            $p = new Patient();
            $p->setFirstName($faker->firstName);
            $p->setLastName($faker->lastName);
            $p->setIdentityDocument($faker->dni);
            $p->setEmail($faker->email);
            $p->setPhone($faker->phoneNumber);
            $p->setAddress($faker->address);
            $p->setRegistrationDate(new \DateTimeImmutable());
            $p->setConsultationReason("Revisión");
            $p->setFamilyHistory("Ninguno");
            $p->setHealthStatus("Bueno");
            $p->setLifestyleHabits("Saludable");
            $p->setMedicationAllergies("Ninguna");
            
            $manager->persist($p);

            $t = new Treatment();
            $t->setTreatmentName("Plan de " . $p->getFirstName());
            $t->setDescription("Tratamiento preventivo");
            $t->setEstimatedDuration(30); 
            $t->setStatus("Activo");
            $manager->persist($t);

            $appointment = new Appointment();
            $appointment->setVisitDate($faker->dateTimeBetween('now', '+1 month'));
            $appointment->setVisitTime($faker->dateTime());
            $appointment->setConsultationReason("Seguimiento");
            $appointment->setObservations($faker->sentence());
            $appointment->setPatient($p);
            $appointment->setDoctor($faker->randomElement($doctors));
            $appointment->setBox($faker->randomElement($boxes));
            $appointment->setStatus("Programada");
            $appointment->setTreatment($t); 
            $appointment->setDurationMinutes(30);
            $manager->persist($appointment);

            // ODONTOGRAM
            $o = new Odontogram();
            $o->setStatus("Pendiente");
            $o->setVisit($appointment);
            $o->setTreatment($t); 
            $manager->persist($o);

            // ODONTOGRAMDETAILS AND PATOLOGÍES
            for ($j = 0; $j < 2; $j++) {
                $det = new OdontogramaDetail();
                $tooth = $faker->boolean(80) ? $faker->numberBetween(11, 48) : null;
                $det->setToothNumber($tooth);
                $det->setOdontograma($o);
                
                $path = new Pathology();
                $path->setDescription("Hallazgo");
                $path->setProtocolColor($faker->hexColor);
                $path->setPathologyType($faker->randomElement($pathologyTypes));
                $path->setTreatment($t); 
                $manager->persist($path);

                $det->setPathology($path);
                $manager->persist($det);

                if ($tooth) {
                    $face = new ToothFace();
                    $face->setFaceName("V");
                    $face->setOdontogramaDetail($det);
                    $manager->persist($face);
                }
            }
        }

        $manager->flush();
    }
}