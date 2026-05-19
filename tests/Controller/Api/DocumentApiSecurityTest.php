<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Document;
use App\Service\DocumentBinaryStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class DocumentApiSecurityTest extends WebTestCase
{
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

        $suffix = bin2hex(random_bytes(6));
        $makePatient = static function (string $tag) use ($client, $suffix): int {
            $body = [
                'identityDocument' => 'DOC-' . $tag . '-' . $suffix,
                'firstName' => 'P',
                'lastName' => $tag,
                'phone' => '60000000' . substr($tag, 0, 1),
                'email' => 'p-' . $tag . '-' . $suffix . '@test.local',
                'address' => 'Addr',
                'consultationReason' => 'r',
                'familyHistory' => 'f',
                'healthStatus' => 'h',
                'lifestyleHabits' => 'l',
                'medicationAllergies' => 'none',
            ];
            $client->request('POST', '/api/patients', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body, \JSON_THROW_ON_ERROR));
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
        $storedName = basename((string) ($docPayload['file_path'] ?? ''));
        self::assertNotSame('', $storedName);
        $diskPath = dirname(__DIR__, 3) . '/public/uploads/documents/' . $storedName;
        self::assertFileExists($diskPath, 'Upload must persist under public/uploads/documents');

        return ['token' => $token, 'patientA' => $idA, 'patientB' => $idB, 'docId' => $docId];
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
        self::assertStringContainsString('application/pdf', (string) $client->getResponse()->headers->get('Content-Type'));

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '/download?patientId=' . $ctx['patientB'], [], [], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '/download', [], [], $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /** Simula otro ordenador: Neon tiene el binario pero no existe el fichero en public/uploads/documents. */
    public function testDownloadServesFromDatabaseWhenLocalFileMissing(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $document = $em->getRepository(Document::class)->find($ctx['docId']);
        self::assertInstanceOf(Document::class, $document);

        $storedName = basename((string) $document->getFilePath());
        self::assertNotSame('', $storedName);

        $diskPath = $client->getContainer()->getParameter('kernel.project_dir')
            . '/public/uploads/documents/' . $storedName;
        if (is_file($diskPath)) {
            unlink($diskPath);
        }
        self::assertFileDoesNotExist($diskPath);

        $blob = DocumentBinaryStorage::normalizeBlob($document->getFileContentRaw());
        self::assertNotNull($blob);
        self::assertNotSame('', $blob);

        $client->request('GET', '/api/documents/' . $ctx['docId'] . '/download?patientId=' . $ctx['patientA'], [], [], $headers);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('%PDF', (string) $client->getResponse()->getContent());
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

    public function testListWithPatient_idQueryParameter(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];

        $client->request('GET', '/api/documents?patient_id=' . $ctx['patientA'], [], [], $headers);
        self::assertResponseIsSuccessful();
        $q = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $q);
    }

    public function testPostRejectsPlainNumericPatientField(): void
    {
        $client = static::createClient();
        $email = 'doc-plain-patient-' . uniqid('', true) . '@falconcare.local';
        $client->request('POST', '/api/auth/register-doctor', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'fullName' => 'Doc P',
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
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['accessToken']];

        $tmp = tempnam(sys_get_temp_dir(), 'doc');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, '%PDF-1.4 test');
        $upload = new UploadedFile($tmp, 'xray.pdf', 'application/pdf', null, true);

        $client->request('POST', '/api/documents', [
            'patient' => '1',
            'type' => 'application/pdf',
        ], ['file' => $upload], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $err = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('DOCUMENT_PATIENT_ABSOLUTE_IRI_REQUIRED', $err['code']);
    }

    public function testPutDescriptionThenDeleteByNestedRoute(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token'],
            'CONTENT_TYPE' => 'application/json',
        ];

        $client->request(
            'PUT',
            '/api/documents/' . $ctx['docId'] . '?patientId=' . $ctx['patientA'],
            [],
            [],
            $headers,
            json_encode(['description' => "Línea 1\nLínea 2"], \JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
        $afterPut = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Línea 1', (string) $afterPut['description']);

        $client->request(
            'DELETE',
            '/api/documents/' . $ctx['patientA'] . '/' . $ctx['docId'],
            [],
            [],
            $headers
        );
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/documents?patientId=' . $ctx['patientA'], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']]);
        self::assertResponseIsSuccessful();
        $list = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(0, $list);
    }

    public function testUploadAcceptsApplicationOctetStreamTypeForPdf(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];
        $apiBase = rtrim((string) (getenv('API_BASE_URL') ?: ($_ENV['API_BASE_URL'] ?? 'http://127.0.0.1:8000')), '/');

        $tmp = tempnam(sys_get_temp_dir(), 'doc2');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, '%PDF-1.4 octet');
        $upload = new UploadedFile($tmp, 'report.pdf', 'application/octet-stream', null, true);

        $client->request('POST', '/api/documents', [
            'patient' => $apiBase . '/api/patients/' . $ctx['patientB'],
            'type' => 'application/octet-stream',
            'description' => 'octet test',
        ], ['file' => $upload], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('application/pdf', $payload['type']);
        self::assertSame($ctx['patientB'], $payload['patientId']);
    }

    public function testOversizeUploadReturns413WithMaxUploadBytes(): void
    {
        $client = static::createClient();
        $ctx = self::doctorTwoPatientsAndOneDoc($client);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $ctx['token']];
        $apiBase = rtrim((string) (getenv('API_BASE_URL') ?: ($_ENV['API_BASE_URL'] ?? 'http://127.0.0.1:8000')), '/');

        $tmp = tempnam(sys_get_temp_dir(), 'big');
        self::assertNotFalse($tmp);
        $maxBytes = (int) (getenv('DOCUMENT_MAX_UPLOAD_BYTES') ?: ($_ENV['DOCUMENT_MAX_UPLOAD_BYTES'] ?? 10_485_760));
        file_put_contents($tmp, "%PDF-1.4\n" . str_repeat('0', $maxBytes + 1));
        $upload = new UploadedFile($tmp, 'big.pdf', 'application/pdf', null, true);

        $client->request('POST', '/api/documents', [
            'patient' => $apiBase . '/api/patients/' . $ctx['patientA'],
            'type' => 'application/pdf',
        ], ['file' => $upload], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        $err = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('maxUploadBytes', $err);
        self::assertSame($maxBytes, $err['maxUploadBytes']);
    }

}
