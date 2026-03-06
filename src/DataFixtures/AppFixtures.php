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

        // 1. BOXES
        $boxes = [];
        for ($i = 1; $i <= 3; $i++) {
            $box = new Box();
            $box->setBoxName("Box $i");
            $box->setStatus(true);
            $box->setCapacity(1);
            $manager->persist($box);
            $boxes[] = $box;
        }

        // 2. DOCTORS
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

        // 3. PATHOLOGIES
        $pathologies = [];
        $defs = [
            ['Caries', '#FF0000', 'fill'],
            ['Limpieza', '#4b11d4', 'line'],
            ['Extraccion', '#000000', 'x_mark'] // Added hex color to maintain array structure
        ];
        foreach ($defs as [$desc, $color, $vType]) {
            $path = new Pathology();
            $path->setDescription($desc);
            $path->setProtocolColor($color);
            $path->setVisualType($vType);
            $manager->persist($path);
            $pathologies[] = $path;
        }

        // 4. USERS (Optional: Creating an Admin user)
        $admin = new User();
        $admin->setEmail('admin@falconcare.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // 5. PATIENTS AND RELATED DATA
        for ($i = 0; $i < 10; $i++) {
            $p = new Patient();
            $p->setFirstName($faker->firstName);
            $p->setLastName($faker->lastName);
            $p->setIdentityDocument($faker->dni);
            $p->setEmail($faker->email);
            $p->setPhone($faker->phoneNumber);
            $p->setAddress($faker->address);
            $p->setRegistrationDate(new \DateTimeImmutable());
            
            // Mandatory text fields according to your entity
            $p->setConsultationReason("Revisión");
            $p->setFamilyHistory("Ninguno");
            $p->setHealthStatus("Bueno");
            $p->setLifestyleHabits("Saludable");
            $p->setMedicationAllergies("Ninguna");
            
            $manager->persist($p);

            // Create a Treatment plan for the patient
            $t = new Treatment();
            $t->setTreatmentName("Plan de " . $p->getFirstName());
            $t->setDescription("Tratamiento preventivo");
            $t->setEstimatedDuration(30);
            $t->setStatus("Activo");
            $manager->persist($t);

            // 6. AGENDA (Create 2 Appointments per patient)
            for ($k = 0; $k < 2; $k++) {
                $appointment = new Appointment();
                $fakeDate = $faker->dateTimeBetween('now', '+1 month');
                $appointment->setVisitDate($fakeDate);
                $appointment->setVisitTime($faker->dateTime());
                $appointment->setConsultationReason($faker->randomElement([
                        'Revisión semestral', 
                        'Dolor agudo en molar', 
                        'Limpieza dental', 
                        'Seguimiento de ortodoncia',
                        'Presupuesto de implante'
                    ]));
                $appointment->setObservations($faker->paragraph(2));                                
                $appointment->setPatient($p);
                $appointment->setDoctor($faker->randomElement($doctors));
                $appointment->setBox($faker->randomElement($boxes));
                $appointment->setStatus($faker->randomElement(['Programada', 'Realizada']));
                // Linking treatment if the relation exists in Appointment
                $appointment->setTreatment($t);
                
                $manager->persist($appointment);

                // 7. ODONTOGRAM (Linked to the Appointment/Visit)
                $o = new Odontogram();
                $o->setStatus("Finalizado");
                $o->setTreatment($t);
                $o->setVisit($appointment); 
                $manager->persist($o);

                // 8. ODONTOGRAM DETAILS (Tooth findings)
                for ($j = 0; $j < 3; $j++) {
                    $det = new OdontogramaDetail();
                    $det->setToothNumber($faker->numberBetween(11, 48));
                    $det->setOdontograma($o);
                    $det->setPathology($faker->randomElement($pathologies));
                    $manager->persist($det);

                    // 9. TOOTH FACES
                    $faces = ['O', 'M', 'D', 'V', 'L'];
                    $randomFaces = $faker->randomElements($faces, $faker->numberBetween(1, 2));

                    foreach ($randomFaces as $faceName) {
                        $face = new ToothFace();
                        $face->setFaceName($faceName);
                        $face->setOdontogramaDetail($det);
                        $manager->persist($face);
                    }
                }
            }
        }

        // Final flush to save everything to the database
        $manager->flush();
    }
}