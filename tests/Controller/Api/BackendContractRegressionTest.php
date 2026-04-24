<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Patient;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group('angular-contract')]
final class BackendContractRegressionTest extends WebTestCase
{
    private static function ensureDoctorToken(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $email = 'contract-doctor-' . uniqid('', true) . '@falconcare.local';
        $password = 'Doctor123!';

        $client->request('POST', '/api/auth/register-doctor', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'fullName' => 'Doctor Contract',
            'email' => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $login = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return (string) $login['accessToken'];
    }

    public function testPatientUpdateAcceptsSnakeFlagsAndKeepsAllergyConsistency(): void
    {
        $client = static::createClient();
        $token = self::ensureDoctorToken($client);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];

        $suffix = substr(uniqid('', true), 0, 8);
        $client->request('POST', '/api/patients', [], [], $headers, json_encode([
            'identityDocument' => 'CON-' . $suffix,
            'firstName' => 'Con',
            'lastName' => 'Tract',
            'phone' => '600100100',
            'email' => 'contract-' . $suffix . '@test.local',
            'address' => 'Addr',
            'consultationReason' => 'General',
            'familyHistory' => 'None',
            'healthStatus' => 'Good',
            'lifestyleHabits' => 'Healthy',
            'medicationAllergies' => 'Penicillin',
            'medication_allergies' => 'Penicillin',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $id = (int) $created['id'];

        $client->request('PUT', '/api/patients/' . $id, [], [], $headers, json_encode([
            'selected_allergies' => [Patient::ALLERGY_PENICILLIN, Patient::ALLERGY_LATEX, Patient::ALLERGY_LATEX],
            'allergies_bitmask' => Patient::ALLERGY_PENICILLIN | Patient::ALLERGY_LATEX,
            'medicationAllergies' => '',
            'medication_allergies' => '',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $updated = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(Patient::ALLERGY_PENICILLIN | Patient::ALLERGY_LATEX, (int) $updated['allergiesBitmask']);
        self::assertContains(Patient::ALLERGY_PENICILLIN, $updated['selectedAllergies']);
        self::assertContains(Patient::ALLERGY_LATEX, $updated['selectedAllergies']);
        self::assertSame('Penicillin, Latex', $updated['medication_allergies']);
    }

    public function testAppointmentIndexValidatesDateAndPatientId(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/appointment/index', ['date' => 'bad-date']);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $invalidDate = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('INVALID_DATE', $invalidDate['code']);

        $client->request('GET', '/api/appointment/index', [
            'date' => '2026-04-24',
            'patientId' => 'abc',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $invalidPatientId = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('VALIDATION_ERROR', $invalidPatientId['code']);
        self::assertSame('patientId', $invalidPatientId['error']['field']);
    }

    public function testSseRouteContractIsRegistered(): void
    {
        $client = static::createClient();
        /** @var RouterInterface $router */
        $router = $client->getContainer()->get(RouterInterface::class);
        $route = $router->getRouteCollection()->get('api_events_sync');
        self::assertNotNull($route);
        self::assertSame('/api/events/sync', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
    }

    public function testAllergyCrudPersistsInNeonAndChecksumChangesOnDirectDbUpdate(): void
    {
        $client = static::createClient();
        $token = self::ensureDoctorToken($client);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        /** @var PatientRepository $repo */
        $repo = $em->getRepository(Patient::class);

        $suffix = substr(uniqid('', true), 0, 8);
        $client->request('POST', '/api/patients', [], [], $headers, json_encode([
            'identityDocument' => 'CRUD-' . $suffix,
            'firstName' => 'Allergy',
            'lastName' => 'Crud',
            'phone' => '699000111',
            'email' => 'allergy-crud-' . $suffix . '@test.local',
            'address' => 'Addr',
            'consultationReason' => 'General',
            'familyHistory' => 'None',
            'healthStatus' => 'Good',
            'lifestyleHabits' => 'Healthy',
            'medicationAllergies' => 'Penicillin',
            'medication_allergies' => 'Penicillin',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $id = (int) $created['id'];

        $em->clear();
        $patient = $repo->find($id);
        self::assertInstanceOf(Patient::class, $patient);
        self::assertSame('Penicillin', $patient->getMedicationAllergies());

        // Edit
        $client->request('PUT', '/api/patients/' . $id, [], [], $headers, json_encode([
            'medicationAllergies' => 'Penicillin, Latex',
            'medication_allergies' => 'Penicillin, Latex',
            'selectedAllergies' => [Patient::ALLERGY_PENICILLIN, Patient::ALLERGY_LATEX],
            'allergiesBitmask' => Patient::ALLERGY_PENICILLIN | Patient::ALLERGY_LATEX,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $em->clear();
        $patientAfterEdit = $repo->find($id);
        self::assertInstanceOf(Patient::class, $patientAfterEdit);
        self::assertSame('Penicillin, Latex', $patientAfterEdit->getMedicationAllergies());
        self::assertSame(Patient::ALLERGY_PENICILLIN | Patient::ALLERGY_LATEX, $patientAfterEdit->getAllergiesBitmask());

        // Delete allergy text (keeps deterministic empty representation)
        $client->request('PUT', '/api/patients/' . $id, [], [], $headers, json_encode([
            'medicationAllergies' => '',
            'medication_allergies' => '',
            'selectedAllergies' => [],
            'allergiesBitmask' => 0,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $em->clear();
        $patientAfterDelete = $repo->find($id);
        self::assertInstanceOf(Patient::class, $patientAfterDelete);
        self::assertSame('', (string) $patientAfterDelete->getMedicationAllergies());
        self::assertSame(0, $patientAfterDelete->getAllergiesBitmask());

        // Simulate external DB change (Neon direct update) and verify checksum delta.
        $before = $repo->getAllergiesStateChecksum();
        $em->getConnection()->executeStatement(
            'UPDATE patient SET medication_allergies = :allergies WHERE id = :id',
            ['allergies' => 'ExternalChange', 'id' => $id]
        );
        $after = $repo->getAllergiesStateChecksum();
        self::assertNotSame($before, $after);
    }
}
