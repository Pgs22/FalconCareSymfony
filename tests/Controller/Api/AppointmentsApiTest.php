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

    private static function getExistingBox(EntityManagerInterface $em, string $boxName): Box
    {
        $box = $em->getRepository(Box::class)->findOneBy(['boxName' => $boxName]);
        self::assertInstanceOf(Box::class, $box, sprintf('Debe existir el box "%s" en la BBDD de test.', $boxName));

        return $box;
    }

    private static function getBoxOne(EntityManagerInterface $em): Box
    {
        return self::getExistingBox($em, 'Box 1');
    }

    private static function getBoxTwo(EntityManagerInterface $em): Box
    {
        return self::getExistingBox($em, 'Box 2');
    }

    private static function uniqueVisitDate(string $suffix, int $offsetDays = 0): string
    {
        $days = (abs((int) crc32($suffix)) % 3000) + $offsetDays;

        return (new \DateTimeImmutable('2035-01-01'))->modify('+'.$days.' days')->format('Y-m-d');
    }

    private static function getDoctorOne(EntityManagerInterface $em): Doctor
    {
        $doctor = $em->getRepository(Doctor::class)->find(1);
        self::assertInstanceOf(Doctor::class, $doctor, 'Debe existir Doctor#1 en la BBDD de test.');

        return $doctor;
    }

    private static function getPatientOne(
        EntityManagerInterface $em,
        ?string $medicationAllergies = 'none',
        ?int $lastOdontogramId = null,
    ): Patient {
        $patient = $em->getRepository(Patient::class)->find(1);
        self::assertInstanceOf(Patient::class, $patient, 'Debe existir Patient#1 en la BBDD de test.');

        $patient->setMedicationAllergies($medicationAllergies ?? 'none');
        $patient->setLastOdontogramId($lastOdontogramId);
        $em->flush();

        return $patient;
    }

    /**
     * @return array{appointment: Appointment, patient: Patient}
     */
    private static function createAppointmentFixture(EntityManagerInterface $em, string $suffix): array
    {
        $box = self::getBoxOne($em);
        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em, 'none', 12345);

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

        $box = self::getBoxOne($em);

        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em, 'none', 12345);

        $treatment = new Treatment();
        $treatment->setTreatmentName('T');
        $treatment->setDescription('D');
        $treatment->setEstimatedDuration(30);
        $treatment->setStatus('Activo');
        $em->persist($treatment);

        $em->flush();

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
        $appointment->setTreatment($treatment);
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
        self::assertSame($treatment->getId(), $row['treatmentId']);
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

        $box = self::getBoxOne($em);

        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em, 'Cap coneguda', null);

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
        $box = self::getBoxOne($em);
        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em, 'none', 12345);
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

    public function testCreateReturnsAllergyAlertAndAcceptsDurationAndCleaningAliases(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $adminEmail = 'appointments-admin-create-alert-'.$suffix.'@test.falconcare.local';
        self::ensureUser($em, $hasher, $adminEmail);

        $box = self::getBoxOne($em);
        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em, 'Penicilina', 12345);

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
        self::assertSame('Programada', $payload['status']);
        self::assertSame(45, $payload['appointment']['durationMinutes']);
        self::assertSame(10, $payload['appointment']['cleaningMinutes']);
        self::assertSame('PATIENT_MEDICATION_ALLERGIES', $payload['alerts'][0]['code']);
        self::assertSame('Penicilina', $payload['alerts'][0]['medicationAllergies']);
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

        $box = self::getBoxOne($em);
        $doctor = self::getDoctorOne($em);
        $otherDoctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em, 'none', 12345);
        $otherPatient = self::getPatientOne($em, 'none', 12345);
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
        $doctor2 = self::getDoctorOne($em);
        $patient1 = self::getPatientOne($em, 'none', 12345);
        $patient2 = self::getPatientOne($em, 'none', 12345);

        $treatment1 = new Treatment();
        $treatment1->setTreatmentName('Treatment 1');
        $treatment1->setDescription('D1');
        $treatment1->setEstimatedDuration(30);
        $treatment1->setStatus('Activo');
        $em->persist($treatment1);

        $treatment2 = new Treatment();
        $treatment2->setTreatmentName('Treatment 2');
        $treatment2->setDescription('D2');
        $treatment2->setEstimatedDuration(45);
        $treatment2->setStatus('Activo');
        $em->persist($treatment2);

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

        $box = self::getBoxOne($em);

        $doctor = self::getDoctorOne($em);
        $patient = self::getPatientOne($em, 'none', 12345);

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
