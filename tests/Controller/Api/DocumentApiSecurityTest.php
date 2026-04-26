<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Box;
use App\Entity\Doctor;
use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class DocumentApiSecurityTest extends WebTestCase
{
    private const TEST_UPLOAD_LIMIT_BYTES = 52428800;

    /**
     * @return array{token: string, patientA: int, patientB: int, docId: int}
     */
    private static function doctorTwoPatientsAndOneDoc(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        $email = 'doc-docs-' . uniqid('', true) . '@falconcare.local';

        $client->request('POST', '/api/auth/register-doctor', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'fullName' => 'Doc Docs',
            'email' => $email,
            'password' => 'Doctor123!',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'Doctor123!',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $auth = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $token = $auth['accessToken'];

        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];

        $suffix = substr(uniqid('', true), 0, 8);
        $makePatient = static function (string $tag) use ($client, $suffix, $headers): int {
            $body = [
                'identityDocument' => 'DOC-' . $tag . '-' . $suffix,
                'firstName' => 'P',
                'lastName' => $tag,
                'phone' => '60000000' . (string) random_int(0, 9),
                'email' => 'p-' . $tag . '-' . $suffix . '@test.local',
                'address' => 'Addr',
                'consultationReason' => 'r',
                'familyHistory' => 'f',
                'healthStatus' => 'h',
                'lifestyleHabits' => 'l',
                'medicationAllergies' => 'none',
            ];
            $client->request(
                'POST',
                '/api/patients',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', ...$headers],
                json_encode($body, \JSON_THROW_ON_ERROR)
            );
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
            $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            return (int) $data['id'];
        };

        $idA = $makePatient('A');
        $idB = $makePatient('B');

        $tmp = tempnam(sys_get_temp_dir(), 'doc');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, '%PDF-1.4 test');
        $upload = new UploadedFile($tmp, 'xray.pdf', 'application/pdf', null, true);

        $apiBase = rtrim((string) (getenv('API_BASE_URL') ?: ($_ENV['API_BASE_URL'] ?? 'http://127.0.0.1:8000')), '/');

        $client->request('POST', '/api/documents', [
            'patient' => $apiBase . '/api/patients/' . $idA,
            'type' => 'application/pdf',
            'description' => 'Test upload',
        ], ['file' => $upload], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $docPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $docId = (int) $docPayload['id'];
        self::assertSame($idA, $docPayload['patient']['id']);

        return ['token' => $token, 'patientA' => $idA, 'patientB' => $idB, 'docId' => $docId];
    }

    private static function createVisitForPatientId(int $patientId): int
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var Patient|null $patient */
        $patient = $em->getRepository(Patient::class)->find($patientId);
        self::assertNotNull($patient);

        /** @var Doctor|null $doctor */
        $doctor = $em->getRepository(Doctor::class)->findOneBy([]);
        self::assertNotNull($doctor);

        /** @var Box|null $box */
        $box = $em->getRepository(Box::class)->findOneBy([]);
        if ($box === null) {
            $box = new Box();
            $box->setBoxName('Box test');
            $box->setStatus(true);
            $box->setCapacity(1);
            $em->persist($box);
            $em->flush();
        }

        $appointment = new Appointment();
        $appointment
            ->setPatient($patient)
            ->setDoctor($doctor)
            ->setBox($box)
            ->setVisitDate(new \DateTime('today'))
            ->setVisitTime(new \DateTime('09:00'))
            ->setDurationMinutes(30)
            ->setConsultationReason('Radiograph annotation test')
            ->setObservations('Test visit')
            ->setStatus('Programada');

        $em->persist($appointment);
        $em->flush();

        self::assertNotNull($appointment->getId());

        return (int) $appointment->getId();
    }

    public function testSubresourceListOnlyReturnsDocumentsForThatPatient(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];

        $client->request('GET', '/api/patients/' . $ctx['patientA'] . '/documents', [], [], $headers);
        self::assertResponseIsSuccessful();
        $listA = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($listA);
        self::assertCount(1, $listA);
        self::assertSame($ctx['docId'], $listA[0]['id']);
        self::assertSame($ctx['patientA'], $listA[0]['patient']['id']);

        $client->request('GET', '/api/patients/' . $ctx['patientB'] . '/documents', [], [], $headers);
        self::assertResponseIsSuccessful();
        $listB = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($listB);
        self::assertCount(0, $listB);
    }

    public function testDocumentsQueryFiltersByPatientIdAndIri(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];
        $apiBase = rtrim((string) (getenv('API_BASE_URL') ?: ($_ENV['API_BASE_URL'] ?? 'http://127.0.0.1:8000')), '/');

        $client->request('GET', '/api/documents?patientId=' . $ctx['patientA'], [], [], $headers);
        self::assertResponseIsSuccessful();
        $q1 = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $q1);

        $client->request('GET', '/api/documents?patient.id=' . $ctx['patientB'], [], [], $headers);
        self::assertResponseIsSuccessful();
        $q2 = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(0, $q2);

        $client->request('GET', '/api/documents?patient_id=' . $ctx['patientA'], [], [], $headers);
        self::assertResponseIsSuccessful();
        $q2b = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $q2b);

        $iri = rawurlencode($apiBase . '/api/patients/' . $ctx['patientA']);
        $client->request('GET', '/api/documents?patient=' . $iri, [], [], $headers);
        self::assertResponseIsSuccessful();
        $q3 = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $q3);
    }

    public function testGlobalDocumentsListWithoutFilterReturns400(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];

        $client->request('GET', '/api/documents', [], [], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testDownloadRequiresMatchingPatientId(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '/download?patientId=' . $ctx['patientA'], [], [], $headers);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '/download?patientId=' . $ctx['patientB'], [], [], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '/download', [], [], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testGetDocumentMetadataRequiresMatchingPatientId(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '?patientId=' . $ctx['patientA'], [], [], $headers);
        self::assertResponseIsSuccessful();
        $meta = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($ctx['docId'], $meta['id']);
        self::assertStringContainsString('/api/patients/' . $ctx['patientA'], (string) $meta['patient']['@id']);

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '?patientId=' . $ctx['patientB'], [], [], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUpdateDescriptionAndReadBackConsistency(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token'],
            'CONTENT_TYPE' => 'application/json',
        ];

        $payload = ['description' => "Nota clínica inicial\nAñadido desde test"];
        $client->request('PUT', '/api/documents/' . $ctx['docId'] . '?patientId=' . $ctx['patientA'], [], [], $headers, json_encode($payload, \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $updated = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($payload['description'], $updated['description']);

        $headersGet = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];
        $client->request('GET', '/api/documents/' . $ctx['docId'] . '?patientId=' . $ctx['patientA'], [], [], $headersGet);
        self::assertResponseIsSuccessful();
        $readBack = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($payload['description'], $readBack['description']);
    }

    public function testDeleteDocumentRemovesRecordAndStopsAppearingInLists(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];

        $client->request('DELETE', '/api/documents/' . $ctx['patientA'] . '/' . $ctx['docId'], [], [], $headers);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '?patientId=' . $ctx['patientA'], [], [], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('GET', '/api/documents?patientId=' . $ctx['patientA'], [], [], $headers);
        self::assertResponseIsSuccessful();
        $list = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(0, $list);
    }

    public function testCreateSupportsNonStandardMimeTypesAndReturnsAliases(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];
        $apiBase = rtrim((string) (getenv('API_BASE_URL') ?: ($_ENV['API_BASE_URL'] ?? 'http://127.0.0.1:8000')), '/');

        $tmp = tempnam(sys_get_temp_dir(), 'doc-any');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, '{"hello":"world"}');
        $upload = new UploadedFile($tmp, 'raw.weird', 'application/x-custom', null, true);

        $client->request('POST', '/api/documents', [
            'patient' => $apiBase . '/api/patients/' . $ctx['patientA'],
            'type' => 'application/x-custom',
            'description' => 'Custom format',
        ], ['file' => $upload], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('application/x-custom', $created['type']);
        self::assertSame('application/x-custom', $created['mimeType']);
        self::assertArrayHasKey('filename', $created);
        self::assertArrayHasKey('fileName', $created);
        self::assertArrayHasKey('url', $created);
        self::assertArrayHasKey('patientId', $created);
    }

    public function testCreateRejectsEmptyFile(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];
        $apiBase = rtrim((string) (getenv('API_BASE_URL') ?: ($_ENV['API_BASE_URL'] ?? 'http://127.0.0.1:8000')), '/');

        $tmp = tempnam(sys_get_temp_dir(), 'doc-empty');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, '');
        $upload = new UploadedFile($tmp, 'empty.txt', 'text/plain', null, true);

        $client->request('POST', '/api/documents', [
            'patient' => $apiBase . '/api/patients/' . $ctx['patientA'],
            'type' => 'text/plain',
        ], ['file' => $upload], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCreateRejectsOversizedFileWith413(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];
        $apiBase = rtrim((string) (getenv('API_BASE_URL') ?: ($_ENV['API_BASE_URL'] ?? 'http://127.0.0.1:8000')), '/');

        $tmp = tempnam(sys_get_temp_dir(), 'doc-big');
        self::assertNotFalse($tmp);
        $fp = fopen($tmp, 'wb');
        self::assertIsResource($fp);
        $chunk = str_repeat('A', 1024 * 1024);
        $written = 0;
        while ($written <= self::TEST_UPLOAD_LIMIT_BYTES) {
            fwrite($fp, $chunk);
            $written += strlen($chunk);
        }
        fclose($fp);
        $upload = new UploadedFile($tmp, 'big.bin', 'application/octet-stream', null, true);

        $client->request('POST', '/api/documents', [
            'patient' => $apiBase . '/api/patients/' . $ctx['patientA'],
            'type' => 'application/octet-stream',
        ], ['file' => $upload], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    public function testDocumentsAndPatientsRequireAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/documents?patientId=1');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request('GET', '/api/patients');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAnnotationPersistenceLinkedToVisitAndPatient(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token'],
            'CONTENT_TYPE' => 'application/json',
        ];
        $visitId = self::createVisitForPatientId($ctx['patientA']);

        $createPayload = [
            'appointmentId' => $visitId,
            'tool' => 'measure',
            'label' => 'Longitud 1',
            'color' => '#22aaee',
            'payload' => [
                'points' => [[10, 20], [140, 70]],
                'value' => 16.4,
                'unit' => 'mm',
            ],
        ];

        $client->request(
            'POST',
            '/api/documents/' . $ctx['docId'] . '/annotations?patientId=' . $ctx['patientA'],
            [],
            [],
            $headers,
            json_encode($createPayload, \JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($ctx['patientA'], $created['patientId']);
        self::assertSame($visitId, $created['appointmentId']);
        self::assertSame('measure', $created['tool']);
        $annotationId = (int) $created['id'];

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '/annotations?patientId=' . $ctx['patientA'], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']]);
        self::assertResponseIsSuccessful();
        $listed = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $listed['items']);
        self::assertSame($annotationId, $listed['items'][0]['id']);

        $updatePayload = [
            'label' => 'Longitud revisada',
            'payload' => [
                'points' => [[10, 20], [150, 80]],
                'value' => 18.0,
                'unit' => 'mm',
            ],
        ];
        $client->request(
            'PUT',
            '/api/documents/' . $ctx['docId'] . '/annotations/' . $annotationId . '?patientId=' . $ctx['patientA'],
            [],
            [],
            $headers,
            json_encode($updatePayload, \JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
        $updated = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Longitud revisada', $updated['label']);
        self::assertEquals(18.0, $updated['payload']['value']);

        $client->request(
            'POST',
            '/api/documents/' . $ctx['docId'] . '/annotations?patientId=' . $ctx['patientA'],
            [],
            [],
            $headers,
            json_encode([
                'appointmentId' => $visitId,
                'tool' => 'note',
                'payload' => ['text' => 'No debe crear con paciente cruzado'],
            ], \JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $other = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $otherId = (int) $other['id'];

        $client->request(
            'GET',
            '/api/documents/' . $ctx['docId'] . '/annotations?patientId=' . $ctx['patientB'],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']]
        );
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request(
            'DELETE',
            '/api/documents/' . $ctx['docId'] . '/annotations/' . $annotationId . '?patientId=' . $ctx['patientA'],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']]
        );
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '/annotations?patientId=' . $ctx['patientA'], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']]);
        self::assertResponseIsSuccessful();
        $afterDelete = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $afterDelete['items']);
        self::assertSame($otherId, $afterDelete['items'][0]['id']);
    }

}
