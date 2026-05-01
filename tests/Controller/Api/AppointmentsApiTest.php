<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Box;
use App\Entity\Doctor;
use App\Entity\Patient;
use App\Entity\Treatment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppointmentsApiTest extends WebTestCase
{
    private static function getAuthHeadersFor(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $email,
        string $password,
    ): array {
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('accessToken', $payload);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$payload['accessToken'],
        ];
    }

    private static function ensureUser(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        string $email,
        array $roles = ['ROLE_ADMIN'],
        string $password = 'secret123',
    ): User {
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            return $user;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRoles($roles);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     * @param list<int> $ids
     *
     * @return T
     */
    private static function getRandomExistingEntityById(EntityManagerInterface $em, string $entityClass, array $ids, string $label): object
    {
        $start = random_int(0, count($ids) - 1);
        $orderedIds = array_merge(array_slice($ids, $start), array_slice($ids, 0, $start));

        foreach ($orderedIds as $id) {
            $entity = $em->getRepository($entityClass)->find($id);
            if ($entity instanceof $entityClass) {
                return $entity;
            }
        }

        self::fail(sprintf('Debe existir al menos un %s con id en [%s] en la BBDD de test.', $label, implode(', ', $ids)));
    }

    private static function getExistingBox(EntityManagerInterface $em, string $boxName): Box
    {
        $box = $em->getRepository(Box::class)->findOneBy(['boxName' => $boxName]);
        self::assertInstanceOf(Box::class, $box, sprintf('Debe existir el box "%s" en la BBDD de test.', $boxName));

        return $box;
    }

    private static function getBoxOne(EntityManagerInterface $em): Box
    {
        /** @var Box $box */
        $box = self::getRandomExistingEntityById($em, Box::class, [1], 'Box');

        return $box;
    }

    private static function getBoxTwo(EntityManagerInterface $em): Box
    {
        /** @var Box $box */
        $box = self::getRandomExistingEntityById($em, Box::class, [2], 'Box');

        return $box;
    }

    private static function getRandomBox(EntityManagerInterface $em): Box
    {
        /** @var Box $box */
        $box = self::getRandomExistingEntityById($em, Box::class, [1, 2], 'Box');

        return $box;
    }

    private static function uniqueVisitDate(string $suffix, int $offsetDays = 0): string
    {
        $days = (abs((int) crc32($suffix)) % 3000) + $offsetDays;

        return (new \DateTimeImmutable('2035-01-01'))->modify('+'.$days.' days')->format('Y-m-d');
    }

    private static function getDoctorOne(EntityManagerInterface $em): Doctor
    {
        /** @var Doctor $doctor */
        $doctor = self::getRandomExistingEntityById($em, Doctor::class, range(1, 6), 'Doctor');

        return $doctor;
    }

    private static function getOtherDoctor(EntityManagerInterface $em, Doctor $current): Doctor
    {
        $ids = array_values(array_filter(range(1, 6), static fn (int $id): bool => $id !== $current->getId()));

        /** @var Doctor $doctor */
        $doctor = self::getRandomExistingEntityById($em, Doctor::class, $ids, 'Doctor');

        return $doctor;
    }

    private static function getPatientOne(EntityManagerInterface $em): Patient
    {
        /** @var Patient $patient */
        $patient = self::getRandomExistingEntityById($em, Patient::class, range(1, 10), 'Patient');

        return $patient;
    }

    private static function getOtherPatient(EntityManagerInterface $em, Patient $current): Patient
    {
        $ids = array_values(array_filter(range(1, 10), static fn (int $id): bool => $id !== $current->getId()));

        /** @var Patient $patient */
        $patient = self::getRandomExistingEntityById($em, Patient::class, $ids, 'Patient');

        return $patient;
    }

    private static function getRandomPatientWithoutLastOdontogram(EntityManagerInterface $em): Patient
    {
        $ids = range(1, 10);
        $start = random_int(0, count($ids) - 1);
        $orderedIds = array_merge(array_slice($ids, $start), array_slice($ids, 0, $start));

        foreach ($orderedIds as $id) {
            $patient = $em->getRepository(Patient::class)->find($id);
            if ($patient instanceof Patient && $patient->getLastOdontogramId() === null) {
                return $patient;
            }
        }

        self::fail('Debe existir al menos un Patient#1..#10 sin lastOdontogramId para probar primera visita.');
    }

    private static function getExistingTreatmentForPatient(Patient $patient): ?Treatment
    {
        foreach ($patient->getAppointments() as $appointment) {
            $treatment = $appointment->getTreatment();
            if ($treatment instanceof Treatment) {
                return $treatment;
            }
        }

        return null;
    }

    private static function patientHasMedicationAllergies(Patient $patient): bool
    {
        $allergies = trim((string) ($patient->getMedicationAllergies() ?? ''));

        return $allergies !== '' && mb_strtolower($allergies) !== mb_strtolower('Cap coneguda');
    }

    /**
     * @return array{appointment: Appointment, patient: Patient}
     */
    private static function createAppointmentFixture(EntityManagerInterface $em, string $suffix): array
    {
        $box = self::getRandomBox($em);
        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em);

        $appointment = new Appointment();
        $appointment->setVisitDate(new \DateTime(self::uniqueVisitDate($suffix)));
        $appointment->setVisitTime(new \DateTime('13:30:00'));
        $appointment->setConsultationReason('Control');
        $appointment->setObservations('');
        $appointment->setStatus('Programada');
        $appointment->setPatient($patient);
        $appointment->setDoctor($doctor);
        $appointment->setBox($box);
        $appointment->setDurationMinutes(30);
        $em->persist($appointment);
        $em->flush();

        return [
            'appointment' => $appointment,
            'patient' => $patient,
        ];
    }

    public function testIndexReturnsAgendaCollection(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/appointment/index');

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
    }

    public function testReadAppointmentReturnsJsonWithAliases(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $box = self::getRandomBox($em);

        $doctor = self::getDoctorOne($em);
        $patient = self::getRandomPatientWithoutLastOdontogram($em);
        $treatment = self::getExistingTreatmentForPatient($patient);

        $appointment = new Appointment();
        $visitDate = self::uniqueVisitDate('READ'.strtoupper(bin2hex(random_bytes(4))));
        $appointment->setVisitDate(new \DateTime($visitDate));
        $appointment->setVisitTime(new \DateTime('10:30:00'));
        $appointment->setConsultationReason('Revisión anual');
        $appointment->setObservations('Sin incidencias');
        $appointment->setStatus('Programada');
        $appointment->setPatient($patient);
        $appointment->setDoctor($doctor);
        $appointment->setBox($box);
        if ($treatment instanceof Treatment) {
            $appointment->setTreatment($treatment);
        }
        $appointment->setDurationMinutes(30);
        $em->persist($appointment);
        $em->flush();

        $pid = (int) $patient->getId();
        self::assertGreaterThan(0, $pid);

        $client->request('GET', '/api/appointment/'.$appointment->getId());
        self::assertResponseIsSuccessful();
        $row = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($appointment->getId(), $row['id']);
        self::assertSame($appointment->getConsultationReason(), $row['reason']);
        self::assertSame($appointment->getConsultationReason(), $row['consultationReason']);
        self::assertSame($visitDate, $row['date']);
        self::assertSame($visitDate, $row['visitDate']);
        self::assertSame('10:30', $row['time']);
        self::assertSame('10:30', $row['visitTime']);
        self::assertSame(30, $row['duration']);
        self::assertSame(30, $row['durationMinutes']);
        self::assertSame($pid, $row['patientId']);
        self::assertSame($doctor->getId(), $row['doctorId']);
        self::assertSame($box->getId(), $row['boxId']);
        self::assertSame($treatment?->getId(), $row['treatmentId']);
    }

    public function testCreateAppointmentSetsMissingConsentStatusForFirstVisit(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $doctorEmail = 'appointments-doctor-first-visit@test.falconcare.local';
        $doctorUser = $em->getRepository(User::class)->findOneBy(['email' => $doctorEmail]);
        if ($doctorUser === null) {
            $doctorUser = new User();
            $doctorUser->setEmail($doctorEmail);
            $doctorUser->setPassword($hasher->hashPassword($doctorUser, 'secret123'));
            $doctorUser->setRoles(['ROLE_DOCTOR']);
            $em->persist($doctorUser);
            $em->flush();
        }

        $box = self::getRandomBox($em);

        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em);

        self::assertNull($patient->getLastOdontogramId());

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $doctorEmail,
            'password' => 'secret123',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $login = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $login['accessToken'],
        ];

        $visitDate = self::uniqueVisitDate('FIRSTVISIT'.strtoupper(bin2hex(random_bytes(4))));

        $client->request('POST', '/api/appointment/create', [], [], $headers, json_encode([
            'visitDate' => $visitDate,
            'visitTime' => '10:30',
            'consultationReason' => 'Primera visita',
            'observations' => '',
            'patient' => $patient->getId(),
            'doctor' => $doctor->getId(),
            'box' => $box->getId(),
            'durationMinutes' => 30,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertArrayHasKey('id', $payload);

        $createdAppointment = $em->getRepository(Appointment::class)->find($payload['id']);
        self::assertInstanceOf(Appointment::class, $createdAppointment);
        self::assertSame('Falta consentiment', $createdAppointment->getStatus());
        self::assertSame('Falta consentiment', $payload['status']);
        self::assertSame('Falta consentiment', $payload['appointment']['status']);
    }

    public function testCreateRejectsOverlappingAppointmentForSameDoctorInDifferentBox(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $adminEmail = 'appointments-admin-doctor-occupied@test.falconcare.local';
        $admin = $em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);
        if ($admin === null) {
            $admin = new User();
            $admin->setEmail($adminEmail);
            $admin->setPassword($hasher->hashPassword($admin, 'secret123'));
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        $suffix = 'DOCBUSY'.strtoupper(bin2hex(random_bytes(4)));
        $fixture = self::createAppointmentFixture($em, $suffix);
        $existingAppointment = $fixture['appointment'];
        $patient = $fixture['patient'];

        $otherBox = self::getBoxTwo($em);

        $headers = self::getAuthHeadersFor($client, $adminEmail, 'secret123');

        $client->request('POST', '/api/appointment/create', [], [], $headers, json_encode([
            'patient' => $patient->getId(),
            'doctor' => $existingAppointment->getDoctor()?->getId(),
            'box' => $otherBox->getId(),
            'visitDate' => $existingAppointment->getVisitDate()?->format('Y-m-d'),
            'visitTime' => '13:45',
            'durationMinutes' => 30,
            'consultationReason' => 'Solape doctor',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('DOCTOR_OCCUPIED', $payload['code']);
        self::assertSame('appointment.doctor.occupied', $payload['error']['messageKey']);
    }

    public function testIndexAndWeeklyEndpointsReturnSerializedAgendaBlocks(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $box = self::getRandomBox($em);
        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em);
        $visitDate = self::uniqueVisitDate('DAY'.$suffix);

        $appointment = new Appointment();
        $appointment->setVisitDate(new \DateTime($visitDate));
        $appointment->setVisitTime(new \DateTime('08:15:00'));
        $appointment->setConsultationReason('Urgencia por dolor');
        $appointment->setObservations('Agenda grid');
        $appointment->setStatus('Programada');
        $appointment->setPatient($patient);
        $appointment->setDoctor($doctor);
        $appointment->setBox($box);
        $appointment->setDurationMinutes(40);
        $appointment->setCleaningMinutes(10);
        $em->persist($appointment);
        $em->flush();

        $client->request('GET', '/api/appointment/index', ['date' => $visitDate]);
        self::assertResponseIsSuccessful();
        $daily = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($daily);
        $dailyById = array_column($daily, null, 'id');
        self::assertArrayHasKey($appointment->getId(), $dailyById);
        $row = $dailyById[$appointment->getId()];
        self::assertSame($visitDate, $row['date']);
        self::assertSame('08:15', $row['time']);
        self::assertSame(40, $row['duration']);
        self::assertSame(10, $row['cleaningMinutes']);
        self::assertSame(50, $row['totalBlockTime']);
        self::assertTrue($row['isUrgency']);

        $client->request('GET', '/api/appointment/weekly', ['date' => $visitDate]);
        self::assertResponseIsSuccessful();
        $weekly = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($weekly);
        self::assertNotEmpty($weekly);
        self::assertContains($appointment->getId(), array_column($weekly, 'id'));

        $client->request('GET', '/api/appointment/weekly', ['date' => 'not-a-date']);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('INVALID_DATE', $payload['code']);
    }

    public function testCreateChecksExistingPatientAllergiesAndAcceptsDurationAndCleaningAliases(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $adminEmail = 'appointments-admin-create-alert-'.$suffix.'@test.falconcare.local';
        self::ensureUser($em, $hasher, $adminEmail);

        $box = self::getRandomBox($em);
        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em);

        $headers = self::getAuthHeadersFor($client, $adminEmail, 'secret123');
        $visitDate = self::uniqueVisitDate('ALERT'.$suffix);

        $client->request('POST', '/api/appointment/create', [], [], $headers, json_encode([
            'visitDate' => $visitDate,
            'visitTime' => '09:00',
            'consultationReason' => 'Control con alergia',
            'observations' => '',
            'patient' => $patient->getId(),
            'doctor' => $doctor->getId(),
            'box' => $box->getId(),
            'duration' => 45,
            'cleaning_time' => 10,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertSame('APPOINTMENT_CREATED', $payload['code']);
        $expectedStatus = $patient->getLastOdontogramId() === null ? 'Falta consentiment' : 'Programada';
        self::assertSame($expectedStatus, $payload['status']);
        self::assertSame(45, $payload['appointment']['durationMinutes']);
        self::assertSame(10, $payload['appointment']['cleaningMinutes']);
        if (self::patientHasMedicationAllergies($patient)) {
            self::assertSame('PATIENT_MEDICATION_ALLERGIES', $payload['alerts'][0]['code']);
            self::assertSame($patient->getMedicationAllergies(), $payload['alerts'][0]['medicationAllergies']);
        } else {
            self::assertArrayNotHasKey('alerts', $payload);
        }
    }

    public function testCreateRejectsInvalidCleaningMinutesAndBoxCleaningOverlap(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $adminEmail = 'appointments-admin-box-overlap-'.$suffix.'@test.falconcare.local';
        self::ensureUser($em, $hasher, $adminEmail);

        $box = self::getRandomBox($em);
        $doctor = self::getDoctorOne($em);
        $otherDoctor = self::getOtherDoctor($em, $doctor);
        $patient = self::getPatientOne($em);
        $otherPatient = self::getOtherPatient($em, $patient);
        $visitDate = self::uniqueVisitDate('BOX'.$suffix);

        $existing = new Appointment();
        $existing->setVisitDate(new \DateTime($visitDate));
        $existing->setVisitTime(new \DateTime('09:00:00'));
        $existing->setConsultationReason('Control');
        $existing->setObservations('');
        $existing->setStatus('Programada');
        $existing->setPatient($patient);
        $existing->setDoctor($doctor);
        $existing->setBox($box);
        $existing->setDurationMinutes(30);
        $existing->setCleaningMinutes(10);
        $em->persist($existing);
        $em->flush();

        $headers = self::getAuthHeadersFor($client, $adminEmail, 'secret123');

        $client->request('POST', '/api/appointment/create', [], [], $headers, json_encode([
            'visitDate' => $visitDate,
            'visitTime' => '10:00',
            'consultationReason' => 'Limpieza invalida',
            'observations' => '',
            'patient' => $otherPatient->getId(),
            'doctor' => $otherDoctor->getId(),
            'box' => $box->getId(),
            'durationMinutes' => 30,
            'cleaningMinutes' => 7,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('VALIDATION_ERROR', $payload['code']);
        self::assertSame('appointment.cleaning_minutes.invalid', $payload['error']['messageKey']);
        self::assertSame([5, 10, 15], $payload['error']['allowedValues']);

        $client->request('POST', '/api/appointment/create', [], [], $headers, json_encode([
            'visitDate' => $visitDate,
            'visitTime' => '09:34',
            'consultationReason' => 'Solape por limpieza',
            'observations' => '',
            'patient' => $otherPatient->getId(),
            'doctor' => $otherDoctor->getId(),
            'box' => $box->getId(),
            'durationMinutes' => 30,
            'cleaningMinutes' => 5,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('BOX_OCCUPIED', $payload['code']);
        self::assertSame('appointment.box.occupied', $payload['error']['messageKey']);
    }

    public function testUpdateAllowsEditingCreateFormFieldsAndDuration(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $adminEmail = 'appointments-admin-update-all-fields@test.falconcare.local';
        $admin = $em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);
        if ($admin === null) {
            $admin = new User();
            $admin->setEmail($adminEmail);
            $admin->setPassword($hasher->hashPassword($admin, 'secret123'));
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        $box1 = self::getBoxOne($em);
        $box2 = self::getBoxTwo($em);

        $doctor1 = self::getDoctorOne($em);
        $doctor2 = self::getOtherDoctor($em, $doctor1);
        $patient1 = self::getPatientOne($em);
        $patient2 = self::getOtherPatient($em, $patient1);

        $treatment1 = self::getExistingTreatmentForPatient($patient1);
        $treatment2 = self::getExistingTreatmentForPatient($patient2);
        self::assertInstanceOf(Treatment::class, $treatment1, 'El paciente inicial debe tener un tratamiento existente en la BBDD de test.');
        self::assertInstanceOf(Treatment::class, $treatment2, 'El paciente destino debe tener un tratamiento existente en la BBDD de test.');

        $visitDate = self::uniqueVisitDate('UPDATE'.strtoupper(bin2hex(random_bytes(4))));
        $updatedVisitDate = (new \DateTimeImmutable($visitDate))->modify('+1 day')->format('Y-m-d');

        $appointment = new Appointment();
        $appointment->setVisitDate(new \DateTime($visitDate));
        $appointment->setVisitTime(new \DateTime('09:30:00'));
        $appointment->setConsultationReason('Initial');
        $appointment->setObservations('Initial obs');
        $appointment->setStatus('Programada');
        $appointment->setPatient($patient1);
        $appointment->setDoctor($doctor1);
        $appointment->setBox($box1);
        $appointment->setTreatment($treatment1);
        $appointment->setDurationMinutes(30);
        $em->persist($appointment);

        $em->flush();

        $headers = self::getAuthHeadersFor($client, $adminEmail, 'secret123');

        $client->request('PUT', '/api/appointment/' . $appointment->getId() . '/update', [], [], $headers, json_encode([
            'visitDate' => $updatedVisitDate,
            'visitTime' => '11:45',
            'consultationReason' => 'Updated reason',
            'observations' => 'Updated obs',
            'status' => 'Confirmada',
            'patient' => $patient2->getId(),
            'doctor' => $doctor2->getId(),
            'box' => $box2->getId(),
            'treatment' => $treatment2->getId(),
            'durationMinutes' => 55,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertSame('APPOINTMENT_UPDATED', $payload['code']);

        $em->clear();
        $updated = $em->getRepository(Appointment::class)->find($appointment->getId());
        self::assertInstanceOf(Appointment::class, $updated);
        self::assertSame($updatedVisitDate, $updated->getVisitDate()?->format('Y-m-d'));
        self::assertSame('11:45', $updated->getVisitTime()?->format('H:i'));
        self::assertSame('Updated reason', $updated->getConsultationReason());
        self::assertSame('Updated obs', $updated->getObservations());
        self::assertSame('Programada', $updated->getStatus());
        self::assertSame(55, $updated->getDurationMinutes());
        self::assertSame($patient2->getId(), $updated->getPatient()?->getId());
        self::assertSame($doctor2->getId(), $updated->getDoctor()?->getId());
        self::assertSame($box2->getId(), $updated->getBox()?->getId());
        self::assertSame($treatment2->getId(), $updated->getTreatment()?->getId());
    }

    public function testManualStatusEndpointAllowsOnlyManualStatuses(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $adminEmail = 'appointments-admin-status@test.falconcare.local';
        $admin = $em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);
        if ($admin === null) {
            $admin = new User();
            $admin->setEmail($adminEmail);
            $admin->setPassword($hasher->hashPassword($admin, 'secret123'));
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        $box = self::getRandomBox($em);

        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em);

        $appointment = new Appointment();
        $appointment->setVisitDate(new \DateTime(self::uniqueVisitDate('STATUS'.strtoupper(bin2hex(random_bytes(4))))));
        $appointment->setVisitTime(new \DateTime('12:30:00'));
        $appointment->setConsultationReason('Control');
        $appointment->setObservations('');
        $appointment->setStatus('Programada');
        $appointment->setPatient($patient);
        $appointment->setDoctor($doctor);
        $appointment->setBox($box);
        $appointment->setDurationMinutes(30);
        $em->persist($appointment);
        $em->flush();

        $headers = self::getAuthHeadersFor($client, $adminEmail, 'secret123');

        $client->request('PATCH', '/api/appointment/'.$appointment->getId().'/status', [], [], $headers, json_encode([
            'status' => 'Arribada',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Arribada', $payload['status']);
        self::assertSame('Arribada', $payload['appointment']['status']);

        $client->request('PATCH', '/api/appointment/'.$appointment->getId().'/status', [], [], $headers, json_encode([
            'status' => 'En curs',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('INVALID_STATUS', $payload['code']);
        self::assertSame(['Confirmada', 'Arribada', 'Cancelada'], $payload['error']['allowedStatuses']);
    }

    public function testStatusesEndpointReturnsManualSelectOptions(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/appointment/statuses');

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertTrue($payload['ok']);
        self::assertSame('APPOINTMENT_STATUSES', $payload['code']);
        self::assertSame(['Confirmada', 'Arribada', 'Cancelada'], $payload['manualStatuses']);
        self::assertContains('Programada', $payload['statuses']);
        self::assertContains('Finalitzada', $payload['statuses']);
    }

    public function testOpenAppointmentSetsAndReturnsInProgressStatus(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $fixture = self::createAppointmentFixture($em, 'OPEN01');
        $appointment = $fixture['appointment'];

        $client->request('GET', '/api/appointment/'.$appointment->getId().'/open');

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('En curs', $payload['status']);
        self::assertSame('En curs', $payload['appointment']['status']);

        $em->clear();
        $updated = $em->getRepository(Appointment::class)->find($appointment->getId());
        self::assertInstanceOf(Appointment::class, $updated);
        self::assertSame('En curs', $updated->getStatus());
    }

    public function testCloseFinishAndDeleteEndpointsUpdateAppointmentLifecycle(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $adminEmail = 'appointments-admin-lifecycle@test.falconcare.local';
        $admin = $em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);
        if ($admin === null) {
            $admin = new User();
            $admin->setEmail($adminEmail);
            $admin->setPassword($hasher->hashPassword($admin, 'secret123'));
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        $fixture = self::createAppointmentFixture($em, 'LIFE'.strtoupper(bin2hex(random_bytes(4))));
        $appointment = $fixture['appointment'];
        $appointmentId = $appointment->getId();
        $headers = self::getAuthHeadersFor($client, $adminEmail, 'secret123');

        self::assertNotNull($appointmentId);

        $client->request('POST', '/api/appointment/'.$appointmentId.'/close');

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertSame('APPOINTMENT_CLOSED', $payload['code']);
        self::assertSame('Finalitzada', $payload['status']);
        self::assertSame('Finalitzada', $payload['appointment']['status']);

        $em->clear();
        $closed = $em->getRepository(Appointment::class)->find($appointmentId);
        self::assertInstanceOf(Appointment::class, $closed);
        self::assertSame('Finalitzada', $closed->getStatus());

        $client->request('PATCH', '/api/appointment/'.$appointmentId.'/finish', [], [], $headers);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('APPOINTMENT_CLOSED', $payload['code']);
        self::assertSame('Finalitzada', $payload['appointment']['status']);

        $client->request('DELETE', '/api/appointment/'.$appointmentId, [], [], $headers);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertSame('APPOINTMENT_DELETED', $payload['code']);
        self::assertSame($appointmentId, $payload['id']);

        $em->clear();
        self::assertNull($em->getRepository(Appointment::class)->find($appointmentId));
    }

    public function testReadNormalizesLegacyAndEmptyStatusesForFrontendDisplay(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $emptyStatusFixture = self::createAppointmentFixture($em, 'EMPTY01');
        $emptyStatusAppointment = $emptyStatusFixture['appointment'];
        $emptyStatusAppointment->setStatus(' ');

        $legacyStatusFixture = self::createAppointmentFixture($em, 'LEGACY01');
        $legacyStatusAppointment = $legacyStatusFixture['appointment'];
        $legacyStatusAppointment->setStatus('Encurs');

        $em->flush();

        $client->request('GET', '/api/appointment/'.$emptyStatusAppointment->getId());
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Programada', $payload['status']);

        $client->request('GET', '/api/appointment/'.$legacyStatusAppointment->getId());
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('En curs', $payload['status']);
    }

    public function testOpeningOdontogramDoesNotChangeAppointmentStatus(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $adminEmail = 'appointments-admin-odontogram-open@test.falconcare.local';
        $admin = $em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);
        if ($admin === null) {
            $admin = new User();
            $admin->setEmail($adminEmail);
            $admin->setPassword($hasher->hashPassword($admin, 'secret123'));
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        $fixture = self::createAppointmentFixture($em, 'ODO01');
        $appointment = $fixture['appointment'];
        $patient = $fixture['patient'];
        $headers = self::getAuthHeadersFor($client, $adminEmail, 'secret123');

        $client->request('POST', '/api/odontograms/open', [], [], $headers, json_encode([
            'patient_id' => $patient->getId(),
            'visit_id' => $appointment->getId(),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('odontogram', $payload);
        self::assertArrayNotHasKey('status', $payload);
        self::assertArrayNotHasKey('appointment', $payload);

        $em->clear();
        $updated = $em->getRepository(Appointment::class)->find($appointment->getId());
        self::assertInstanceOf(Appointment::class, $updated);
        self::assertSame('Programada', $updated->getStatus());
    }
}
