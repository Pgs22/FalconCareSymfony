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
use App\Entity\PathologyType; // IMPORTANTE
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

        // 3. PATHOLOGY TYPES (El Catálogo / Moldes)
        $pathologyTypes = [];
        $catalogo = [
            ['Caries', 30],
            ['Limpieza', 30],
            ['Endodoncia', 60],
            ['Extracción', 30]
        ];

        foreach ($catalogo as [$nombre, $duracion]) {
            $type = new PathologyType();
            $type->setName($nombre);
            $type->setDefaultDuration($duracion);
            $manager->persist($type);
            $pathologyTypes[] = $type;
        }

        // 4. USERS
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
            
            $p->setConsultationReason("Revisión");
            $p->setFamilyHistory("Ninguno");
            $p->setHealthStatus("Bueno");
            $p->setLifestyleHabits("Saludable");
            $p->setMedicationAllergies("Ninguna");
            
            $manager->persist($p);

            // Crear Tratamiento para el paciente
            $t = new Treatment();
            $t->setTreatmentName("Plan de " . $p->getFirstName());
            $t->setDescription("Tratamiento preventivo");
            $t->setEstimatedDuration(30); // Luego lo calcularemos dinámicamente
            $t->setStatus("Activo");
            $t->setSchedulingNotes("Paciente requiere atención especial en molares");
            $manager->persist($t);

            // 6. CREAR PATOLOGÍAS REALES vinculadas al Tratamiento
            $pathologiesOfThisTreatment = [];
            for ($x = 0; $x < 2; $x++) {
                $type = $faker->randomElement($pathologyTypes);
                
                $path = new Pathology();
                $path->setDescription($type->getName());
                $path->setProtocolColor($faker->hexColor);
                $path->setVisualType($faker->randomElement(['fill', 'line', 'x_mark']));
                $path->setPathologyType($type); // Relación ManyToOne
                $path->setTreatment($t);       // Relación ManyToOne (NUEVA)
                
                $manager->persist($path);
                $pathologiesOfThisTreatment[] = $path;
            }

            // 7. APPOINTMENTS (Citas)
            for ($k = 0; $k < 1; $k++) {
                $appointment = new Appointment();
                $fakeDate = $faker->dateTimeBetween('now', '+1 month');
                $appointment->setVisitDate($fakeDate);
                $appointment->setVisitTime($faker->dateTime());
                $appointment->setConsultationReason("Cita de seguimiento");
                $appointment->setObservations($faker->paragraph(1));
                $appointment->setPatient($p);
                $appointment->setDoctor($faker->randomElement($doctors));
                $appointment->setBox($faker->randomElement($boxes));
                $appointment->setStatus($faker->randomElement(['Programada', 'Realizada']));
                $appointment->setTreatment($t);
                $appointment->setDurationMinutes(30); // Obligatorio en tu migración
                
                $manager->persist($appointment);

                // 8. ODONTOGRAM
                $o = new Odontogram();
                $o->setStatus("En proceso");
                $o->setTreatment($t);
                $o->setVisit($appointment); 
                $manager->persist($o);

                // 9. ODONTOGRAM DETAILS (Relacionado con las patologías del tratamiento)
                foreach ($pathologiesOfThisTreatment as $path) {
                    $det = new OdontogramaDetail();
                    $det->setToothNumber($faker->numberBetween(11, 48));
                    $det->setOdontograma($o);
                    $det->setPathology($path);
                    $manager->persist($det);

                    // 10. TOOTH FACES
                    $face = new ToothFace();
                    $face->setFaceName($faker->randomElement(['O', 'M', 'D', 'V', 'L']));
                    $face->setOdontogramaDetail($det);
                    $manager->persist($face);
                }
            }
        }

        $manager->flush();
    }
}