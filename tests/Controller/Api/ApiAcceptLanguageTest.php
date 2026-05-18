<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprueba que {@see \App\EventSubscriber\ApiLocaleSubscriber} y el dominio de traducción {@code api}
 * alinean las respuestas JSON con {@code Accept-Language} (como envía el frontend Angular).
 *
 * Frontend (repo hermano FalconCare): {@code src/app/interceptors/locale.interceptor.ts},
 * {@code src/app/services/language.service.ts}, registro en {@code src/app/app.config.ts}.
 */
final class ApiAcceptLanguageTest extends WebTestCase
{
    /**
     * @return array{\Symfony\Bundle\FrameworkBundle\KernelBrowser, string}
     */
    private static function createAuthenticatedDoctor(): array
    {
        $client = static::createClient();
        $email = 'doc-accept-lang-' . uniqid('', true) . '@falconcare.local';
        $client->request('POST', '/api/auth/register-doctor', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'fullName' => 'Doc Accept Lang',
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

        return [$client, 'Bearer ' . $auth['accessToken']];
    }

    public function testDocumentsListWithoutFilterLocalizesMessageAndHttpLineByAcceptLanguage(): void
    {
        [$client, $bearer] = self::createAuthenticatedDoctor();
        $authHeaders = ['HTTP_AUTHORIZATION' => $bearer];

        $client->request('GET', '/api/documents', [], [], array_merge($authHeaders, [
            'HTTP_ACCEPT_LANGUAGE' => 'ca',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $ca = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('DOCUMENT_PATIENT_FILTER_REQUIRED', $ca['code']);
        self::assertStringContainsString('Indiqueu filtre de pacient', (string) $ca['message']);
        self::assertSame('Sol·licitud incorrecta', $ca['error']);

        $client->request('GET', '/api/documents', [], [], array_merge($authHeaders, [
            'HTTP_ACCEPT_LANGUAGE' => 'es',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $es = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('DOCUMENT_PATIENT_FILTER_REQUIRED', $es['code']);
        self::assertStringContainsString('Indique filtro de paciente', (string) $es['message']);
        self::assertSame('Solicitud incorrecta', $es['error']);

        $client->request('GET', '/api/documents', [], [], array_merge($authHeaders, [
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $en = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Provide patientId', (string) $en['message']);
        self::assertSame('Bad Request', $en['error']);

        $client->request('GET', '/api/documents', [], [], array_merge($authHeaders, [
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $fr = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Indiquez un filtre patient', (string) $fr['message']);
        self::assertSame('Requête incorrecte', $fr['error']);

        self::assertNotSame($ca['message'], $es['message']);
        self::assertNotSame($es['message'], $en['message']);
        self::assertNotSame($en['message'], $fr['message']);
    }

    public function testLocaleQueryParameterOverridesAcceptLanguage(): void
    {
        [$client, $bearer] = self::createAuthenticatedDoctor();
        $authHeaders = ['HTTP_AUTHORIZATION' => $bearer];

        $client->request('GET', '/api/documents?locale=fr', [], [], array_merge($authHeaders, [
            'HTTP_ACCEPT_LANGUAGE' => 'ca',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Indiquez un filtre patient', (string) $payload['message']);
        self::assertSame('Requête incorrecte', $payload['error']);
    }
}
