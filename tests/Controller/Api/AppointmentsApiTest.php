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

    /**
     * @return array{appointment: Appointment, patient: Patient}
     */
    private static function createAppointmentFixture(EntityManagerInterface $em, string $suffix): array
    {
        $box = new Box();
        $box->setBoxName('AutoBox-'.$suffix);
        $box->setStatus(true);
        $box->setCapacity(1);
        $em->persist($box);

        $doctor = new Doctor();
        $doctor->setFirstName('Auto');
        $doctor->setLastNames('Doctor '.$suffix);
        $doctor->setSpecialty('General');
        $doctor->setPhone('600'.$suffix);
        $doctor->setEmail('auto-doctor-'.$suffix.'@test.local');
        $em->persist($doctor);

        $patient = new Patient();
        $patient->setIdentityDocument('AUTO'.$suffix);
        $patient->setFirstName('Auto');
        $patient->setLastName('Patient '.$suffix);
        $patient->setPhone('611'.$suffix);
        $patient->setEmail('auto-patient-'.$suffix.'@test.local');
        $patient->setAddress('Addr');
        $patient->setConsultationReason('Control');
        $patient->setFamilyHistory('None');
        $patient->setHealthStatus('Good');
        $patient->setLifestyleHabits('Good');
        $patient->setMedicationAllergies('none');
        $patient->setRegistrationDate(new \DateTimeImmutable());
        $em->persist($patient);

        $appointment = new Appointment();
        $appointment->setVisitDate(new \DateTime('2026-04-24'));
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

    public function testListRequiresPatientFilter(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $email = 'appointments-admin-filter@test.falconcare.local';
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user === null) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($hasher->hashPassword($user, 'secret123'));
            $user->setRoles(['ROLE_ADMIN']);
            $em->persist($user);
            $em->flush();
        }

        $headers = self::getAuthHeadersFor($client, $email, 'secret123');
        $client->request('GET', '/api/appointments', [], [], $headers);

        self::assertResponseStatusCodeSame(400);
    }

    public function testListByPatientIdReturnsJsonWithAliases(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $adminEmail = 'appointments-admin-list@test.falconcare.local';
        $admin = $em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);
        if ($admin === null) {
            $admin = new User();
            $admin->setEmail($adminEmail);
            $admin->setPassword($hasher->hashPassword($admin, 'secret123'));
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        $box = new Box();
        $box->setBoxName('TBox');
        $box->setStatus(true);
        $box->setCapacity(1);
        $em->persist($box);

        $doctor = new Doctor();
        $doctor->setFirstName('Ann');
        $doctor->setLastNames('Doe');
        $doctor->setSpecialty('Test');
        $doctor->setPhone('600000000');
        $doctor->setEmail('doc-appoint-test@local.test');
        $em->persist($doctor);

        $patient = new Patient();
        $patient->setIdentityDocument('99999999T');
        $patient->setFirstName('Pat');
        $patient->setLastName('Ient');
        $patient->setPhone('611111111');
        $patient->setEmail('pat@test.local');
        $patient->setAddress('Addr');
        $patient->setConsultationReason('x');
        $patient->setFamilyHistory('x');
        $patient->setHealthStatus('x');
        $patient->setLifestyleHabits('x');
        $patient->setMedicationAllergies('none');
        $patient->setRegistrationDate(new \DateTimeImmutable());
        $em->persist($patient);

        $treatment = new Treatment();
        $treatment->setTreatmentName('T');
        $treatment->setDescription('D');
        $treatment->setEstimatedDuration(30);
        $treatment->setStatus('Activo');
        $em->persist($treatment);

        $em->flush();

        $appointment = new Appointment();
        $appointment->setVisitDate(new \DateTime('2026-04-15'));
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

        $headers = self::getAuthHeadersFor($client, $adminEmail, 'secret123');

        $client->request('GET', '/api/appointments', ['patientId' => $pid], [], $headers);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertCount(1, $data);
        $row = $data[0];
        self::assertSame($appointment->getId(), $row['id']);
        self::assertSame('Revisión anual', $row['reason']);
        self::assertArrayHasKey('startTime', $row);

        $client->request('GET', '/api/appointments', ['patient.id' => $pid], [], $headers);
        self::assertResponseIsSuccessful();
        $data2 = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $data2);

        $client->request('GET', '/api/appointments', ['patient' => '/api/patients/'.$pid], [], $headers);
        self::assertResponseIsSuccessful();
        $data3 = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $data3);

        $client->request('GET', '/api/appointments', ['patientId' => $pid, 'format' => 'jsonld'], [], $headers);
        self::assertResponseIsSuccessful();
        $ld = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('hydra:member', $ld);
        self::assertCount(1, $ld['hydra:member']);
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

        $box = new Box();
        $box->setBoxName('ConsentBox');
        $box->setStatus(true);
        $box->setCapacity(1);
        $em->persist($box);

        $doctor = new Doctor();
        $doctor->setFirstName('Laura');
        $doctor->setLastNames('Gomez');
        $doctor->setSpecialty('General');
        $doctor->setPhone('622222222');
        $doctor->setEmail('laura-gomez@test.local');
        $em->persist($doctor);

        $patient = new Patient();
        $patient->setIdentityDocument('FIRSTVISIT1');
        $patient->setFirstName('New');
        $patient->setLastName('Patient');
        $patient->setPhone('633333333');
        $patient->setEmail('new-patient@test.local');
        $patient->setAddress('Addr');
        $patient->setConsultationReason('Primera visita');
        $patient->setFamilyHistory('None');
        $patient->setHealthStatus('Healthy');
        $patient->setLifestyleHabits('Good');
        $patient->setMedicationAllergies('Cap coneguda');
        $patient->setRegistrationDate(new \DateTimeImmutable());
        $em->persist($patient);

        $em->flush();

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

        $client->request('POST', '/api/appointment/create', [], [], $headers, json_encode([
            'visitDate' => '2026-04-20',
            'visitTime' => '10:30',
            'consultationReason' => 'Primera visita',
            'observations' => '',
            'patient' => $patient->getId(),
            'doctor' => $doctor->getId(),
            'box' => $box->getId(),
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

        $box1 = new Box();
        $box1->setBoxName('U-Box-1');
        $box1->setStatus(true);
        $box1->setCapacity(1);
        $em->persist($box1);

        $box2 = new Box();
        $box2->setBoxName('U-Box-2');
        $box2->setStatus(true);
        $box2->setCapacity(1);
        $em->persist($box2);

        $doctor1 = new Doctor();
        $doctor1->setFirstName('Doc');
        $doctor1->setLastNames('One');
        $doctor1->setSpecialty('General');
        $doctor1->setPhone('644444441');
        $doctor1->setEmail('doc-one-update@test.local');
        $em->persist($doctor1);

        $doctor2 = new Doctor();
        $doctor2->setFirstName('Doc');
        $doctor2->setLastNames('Two');
        $doctor2->setSpecialty('General');
        $doctor2->setPhone('644444442');
        $doctor2->setEmail('doc-two-update@test.local');
        $em->persist($doctor2);

        $patient1 = new Patient();
        $patient1->setIdentityDocument('UPDTEST01A');
        $patient1->setFirstName('Pat');
        $patient1->setLastName('One');
        $patient1->setPhone('655555551');
        $patient1->setEmail('pat-one-update@test.local');
        $patient1->setAddress('Addr 1');
        $patient1->setConsultationReason('Init');
        $patient1->setFamilyHistory('None');
        $patient1->setHealthStatus('Good');
        $patient1->setLifestyleHabits('Good');
        $patient1->setMedicationAllergies('none');
        $patient1->setRegistrationDate(new \DateTimeImmutable());
        $em->persist($patient1);

        $patient2 = new Patient();
        $patient2->setIdentityDocument('UPDTEST02B');
        $patient2->setFirstName('Pat');
        $patient2->setLastName('Two');
        $patient2->setPhone('655555552');
        $patient2->setEmail('pat-two-update@test.local');
        $patient2->setAddress('Addr 2');
        $patient2->setConsultationReason('Init');
        $patient2->setFamilyHistory('None');
        $patient2->setHealthStatus('Good');
        $patient2->setLifestyleHabits('Good');
        $patient2->setMedicationAllergies('none');
        $patient2->setRegistrationDate(new \DateTimeImmutable());
        $em->persist($patient2);

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

        $appointment = new Appointment();
        $appointment->setVisitDate(new \DateTime('2026-04-21'));
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
            'visitDate' => '2026-04-22',
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
        self::assertSame('2026-04-22', $updated->getVisitDate()?->format('Y-m-d'));
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

        $box = new Box();
        $box->setBoxName('StatusBox');
        $box->setStatus(true);
        $box->setCapacity(1);
        $em->persist($box);

        $doctor = new Doctor();
        $doctor->setFirstName('Status');
        $doctor->setLastNames('Doctor');
        $doctor->setSpecialty('General');
        $doctor->setPhone('666666666');
        $doctor->setEmail('status-doctor@test.local');
        $em->persist($doctor);

        $patient = new Patient();
        $patient->setIdentityDocument('STATUS01');
        $patient->setFirstName('Status');
        $patient->setLastName('Patient');
        $patient->setPhone('677777777');
        $patient->setEmail('status-patient@test.local');
        $patient->setAddress('Addr');
        $patient->setConsultationReason('Control');
        $patient->setFamilyHistory('None');
        $patient->setHealthStatus('Good');
        $patient->setLifestyleHabits('Good');
        $patient->setMedicationAllergies('none');
        $patient->setRegistrationDate(new \DateTimeImmutable());
        $em->persist($patient);

        $appointment = new Appointment();
        $appointment->setVisitDate(new \DateTime('2026-04-23'));
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
