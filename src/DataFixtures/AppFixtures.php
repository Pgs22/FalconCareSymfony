<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Patient;
use App\Entity\Appointment;
use App\Entity\Box;
use App\Entity\Doctor;
use App\Entity\Odontogram;
use App\Entity\OdontogramDetail;
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
        for ($i = 1; $i <= 2; $i++) {
            $box = new Box();
            $box->setBoxName("Box $i");
            $box->setStatus(true);
            $box->setCapacity(1);
            $manager->persist($box);
            $boxes[] = $box;
        }

        $doctors = [];
        $daysPossible = ['dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres'];

        for ($i = 0; $i < 5; $i++) {
            $dr = new Doctor();
            $dr->setFirstName($faker->firstName);
            $dr->setLastNames($faker->lastName . " " . $faker->lastName);
            $dr->setSpecialty("Odontologia General");
            $dr->setEmail($faker->email);
            $dr->setPhone($faker->phoneNumber);            
            $dayAssign = $daysPossible[$i];
            $dr->setAssignedWeekday($dayAssign);            
            $manager->persist($dr);
            $doctors[] = $dr;
        }

        $pathologyTypes = [];
        $catalogo = [['Càries', 30], ['Neteja', 30], ['Endodòncia', 60]];
        foreach ($catalogo as [$nombre, $duracion]) {
            $type = new PathologyType();
            $type->setName($nombre);
            $type->setDefaultDuration($duracion);
            $manager->persist($type);
            $pathologyTypes[$nombre] = $type;
        }

        $admin = new User();
        $admin->setEmail('admin@falconcare.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        for ($i = 0; $i < 10; $i++) {
            $p = new Patient();
            $p->setFirstName($faker->firstName);
            $p->setLastName($faker->lastName);
            $p->setIdentityDocument($faker->dni);
            $p->setSsNumber($faker->numerify('###########')); 
            $p->setEmail($faker->email);
            $p->setPhone($faker->phoneNumber);
            $p->setAddress($faker->address);
            
            $p->setRegistrationDate(new \DateTimeImmutable());
            
            $p->setConsultationReason("Revisió de rutina");
            $p->setFamilyHistory("Cap rellevant");
            $p->setHealthStatus("Bon estat general");
            $p->setLifestyleHabits("Saludable");
            $p->setMedicationAllergies("Cap coneguda");
            $manager->persist($p);

            $t = new Treatment();
            $t->setTreatmentName("Pla Preventiu - " . $p->getFirstName());
            $t->setDescription("Seguiment anual i neteja");
            $t->setEstimatedDuration(30);
            $t->setStatus("Actiu");
            $manager->persist($t);

            $date = $faker->dateTimeBetween('now', '+1 month');
            $appointment = new Appointment();
            $appointment->setVisitDate($date); 
            $appointment->setVisitTime($date); 
            $appointment->setConsultationReason("Revisió");
            $appointment->setObservations("Pacient citat per control");
            $appointment->setStatus("Programada");
            $appointment->setDurationMinutes(30);
            $appointment->setPatient($p);
            $appointment->setDoctor($faker->randomElement($doctors));
            $appointment->setBox($faker->randomElement($boxes));
            $appointment->setTreatment($t);
            $manager->persist($appointment);

            $o = new Odontogram();
            $o->setStatus("Pendiente");
            $o->setVisit($appointment);
            $o->setTreatment($t);
            $manager->persist($o);

            if ($i === 0) {
                $this->addTestData($manager, $o, $pathologyTypes['Càries'], $t);
            }
        }

        $manager->flush();
    }

    private function addTestData($manager, $o, $type, $t)
    {
        $path = new Pathology();
        $path->setDescription("Càries profunda detectada");
        $path->setProtocolColor("#FF0000");
        $path->setPathologyType($type);
        $path->setTreatment($t);
        $path->setVisualType("Troballa"); 
        $manager->persist($path);

        $det = new OdontogramDetail();
        $det->setToothNumber(16);
        $det->setOdontogram($o);
        $det->setPathology($path);
        $manager->persist($det);

        foreach (['O', 'M', 'D'] as $faceName) {
            $face = new ToothFace();
            $face->setFaceName($faceName);
            $face->setOdontogramDetail($det);
            $manager->persist($face);
        }
    }
}