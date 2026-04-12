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
}
