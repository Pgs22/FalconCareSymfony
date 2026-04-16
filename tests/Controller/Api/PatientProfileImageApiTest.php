<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Patient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PatientProfileImageApiTest extends WebTestCase
{
    private static function ensureDoctorAndToken(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        $email = 'patient-img-' . uniqid('', true) . '@falconcare.local';
        $pass = 'Doctor123!';
        $client->request('POST', '/api/auth/register-doctor', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'fullName' => 'Doctor Test Img',
            'email' => $email,
            'password' => $pass,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $pass,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $auth = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return ['token' => $auth['accessToken'], 'email' => $email];
    }

    private static function authHeaders(string $token): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];
    }

    /**
     * @return array{id: int}
     */
    private static function createPatientViaPost(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $token): array
    {
        $suffix = substr(uniqid('', true), 0, 8);
        $body = [
            'identityDocument' => 'ID-' . $suffix,
            'firstName' => 'Pat',
            'lastName' => 'Ient',
            'phone' => '600000000',
            'email' => 'patient-' . $suffix . '@test.local',
            'address' => 'Addr',
            'consultationReason' => 'r',
            'familyHistory' => 'f',
            'healthStatus' => 'h',
            'lifestyleHabits' => 'l',
            'medicationAllergies' => 'none',
        ];
        $client->request('POST', '/api/patients', [], [], self::authHeaders($token), json_encode($body, \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $data);

        return ['id' => (int) $data['id']];
    }

    public function testPutProfileImagePersistsAndGetReturnsCanonicalAndAliases(): void
    {
        $client = static::createClient();
        $auth = self::ensureDoctorAndToken($client);
        $patient = self::createPatientViaPost($client, $auth['token']);
        $headers = self::authHeaders($auth['token']);
        $img = 'data:image/png;base64,PATIENT_IMG_OK';

        $client->request('PUT', '/api/patients/' . $patient['id'], [], [], $headers, json_encode([
            'profile_image' => $img,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $put = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($img, $put['profile_image']);
        self::assertSame($img, $put['profile_image_url']);
        self::assertSame($img, $put['profileImage']);

        $client->request('GET', '/api/patients/' . $patient['id'], [], [], $headers);
        self::assertResponseIsSuccessful();
        $get = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($img, $get['profile_image']);
        self::assertSame($img, $get['profileImageUrl']);

        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $p = $em->getRepository(Patient::class)->find($patient['id']);
        self::assertInstanceOf(Patient::class, $p);
        self::assertSame($img, $p->getProfileImage());
    }

    public function testPutProfileImageAliasProfileImageCompat(): void
    {
        $client = static::createClient();
        $auth = self::ensureDoctorAndToken($client);
        $patient = self::createPatientViaPost($client, $auth['token']);
        $headers = self::authHeaders($auth['token']);
        $img = 'data:image/jpeg;base64,ALIAS_ONLY';

        $client->request('PUT', '/api/patients/' . $patient['id'], [], [], $headers, json_encode([
            'profileImage' => $img,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $put = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($img, $put['profile_image']);
    }

    public function testPutProfileImageTooLargeReturns400(): void
    {
        $client = static::createClient();
        $auth = self::ensureDoctorAndToken($client);
        $patient = self::createPatientViaPost($client, $auth['token']);
        $headers = self::authHeaders($auth['token']);

        $client->request('PUT', '/api/patients/' . $patient['id'], [], [], $headers, json_encode([
            'profile_image' => str_repeat('X', 2_000_001),
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testPutProfileImageNullClearsColumn(): void
    {
        $client = static::createClient();
        $auth = self::ensureDoctorAndToken($client);
        $patient = self::createPatientViaPost($client, $auth['token']);
        $headers = self::authHeaders($auth['token']);
        $img = 'data:image/png;base64,BEFORE_NULL';

        $client->request('PUT', '/api/patients/' . $patient['id'], [], [], $headers, json_encode([
            'profile_image' => $img,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('PUT', '/api/patients/' . $patient['id'], [], [], $headers, json_encode([
            'profile_image' => null,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $put = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNull($put['profile_image']);
        self::assertNull($put['profileImageUrl']);

        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $p = $em->getRepository(Patient::class)->find($patient['id']);
        self::assertInstanceOf(Patient::class, $p);
        self::assertNull($p->getProfileImage());
    }

    public function testPatchProfileImagePersists(): void
    {
        $client = static::createClient();
        $auth = self::ensureDoctorAndToken($client);
        $patient = self::createPatientViaPost($client, $auth['token']);
        $headers = self::authHeaders($auth['token']);
        $img = 'data:image/webp;base64,PATCH_OK';

        $client->request('PATCH', '/api/patients/' . $patient['id'], [], [], $headers, json_encode([
            'profile_image' => $img,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($img, $data['profile_image']);
    }

    public function testPutPatientForbiddenForRoleUserOnly(): void
    {
        $client = static::createClient();
        $auth = self::ensureDoctorAndToken($client);
        $patient = self::createPatientViaPost($client, $auth['token']);

        $plainUserEmail = 'plain-user-patient-put@test.falconcare.local';
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $plainUserEmail]);
        if ($existing === null) {
            $u = new User();
            $u->setEmail($plainUserEmail);
            $u->setPassword($hasher->hashPassword($u, 'user12345'));
            $u->setRoles([]);
            $em->persist($u);
            $em->flush();
        }

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $plainUserEmail,
            'password' => 'user12345',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $login = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $userHeaders = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $login['accessToken'],
        ];

        $client->request('PUT', '/api/patients/' . $patient['id'], [], [], $userHeaders, json_encode([
            'profile_image' => 'data:image/png;base64,FORBIDDEN',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $err = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Forbidden', $err['error']);
    }

    public function testMedicationAllergiesStillWorksOnPut(): void
    {
        $client = static::createClient();
        $auth = self::ensureDoctorAndToken($client);
        $patient = self::createPatientViaPost($client, $auth['token']);
        $headers = self::authHeaders($auth['token']);

        $client->request('PUT', '/api/patients/' . $patient['id'], [], [], $headers, json_encode([
            'medicationAllergies' => 'PENICILINA',
            'medication_allergies' => 'PENICILINA',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('PENICILINA', $data['medicationAllergies']);
    }
}
