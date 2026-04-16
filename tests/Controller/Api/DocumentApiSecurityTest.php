<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

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

        $suffix = substr(uniqid('', true), 0, 8);
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

}
