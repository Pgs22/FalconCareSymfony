<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserControllerTest extends WebTestCase
{
    private static function ensureUserExists(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $email,
        string $password,
        array $roles
    ): User
    {
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing !== null) {
            return $existing;
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
     * @return array<string,string>
     */
    private static function getAuthHeadersFor(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email, string $password): array
    {
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('accessToken', $payload);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $payload['accessToken'],
        ];
    }

    public function testGetUserReturnsProfileImageField(): void
    {
        $client = static::createClient();
        $doctor = self::ensureUserExists(
            $client,
            'doctor-profile-get@test.falconcare.local',
            'doctor123',
            ['ROLE_DOCTOR', 'ROLE_USER']
        );
        $doctor->setProfileImage('data:image/png;base64,AAA');
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->flush();

        $headers = self::getAuthHeadersFor($client, 'doctor-profile-get@test.falconcare.local', 'doctor123');
        $client->request('GET', '/api/users/' . $doctor->getId(), [], [], $headers);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('profile_image', $data);
        self::assertSame('data:image/png;base64,AAA', $data['profile_image']);
    }

    public function testUpdateOwnUserProfileImageSuccess(): void
    {
        $client = static::createClient();
        $doctor = self::ensureUserExists(
            $client,
            'doctor-self-update@test.falconcare.local',
            'doctor123',
            ['ROLE_DOCTOR', 'ROLE_USER']
        );
        $headers = self::getAuthHeadersFor($client, 'doctor-self-update@test.falconcare.local', 'doctor123');
        $client->request('PUT', '/api/users/' . $doctor->getId(), [], [], $headers, json_encode([
            'profile_image' => 'data:image/png;base64,BBB',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('data:image/png;base64,BBB', $payload['profile_image']);

        $reloaded = $client->getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->find($doctor->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('data:image/png;base64,BBB', $reloaded->getProfileImage());
    }

    public function testUpdateOtherUserProfileImageForbiddenForDoctor(): void
    {
        $client = static::createClient();
        self::ensureUserExists(
            $client,
            'doctor-owner@test.falconcare.local',
            'doctor123',
            ['ROLE_DOCTOR', 'ROLE_USER']
        );
        $target = self::ensureUserExists(
            $client,
            'doctor-target@test.falconcare.local',
            'doctor123',
            ['ROLE_DOCTOR', 'ROLE_USER']
        );

        $headers = self::getAuthHeadersFor($client, 'doctor-owner@test.falconcare.local', 'doctor123');
        $client->request('PUT', '/api/users/' . $target->getId(), [], [], $headers, json_encode([
            'profile_image' => 'data:image/png;base64,CCC',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Forbidden', $payload['error']);
    }

    public function testUpdateOwnUserProfileImageTooLargeReturns400(): void
    {
        $client = static::createClient();
        $doctor = self::ensureUserExists(
            $client,
            'doctor-too-large@test.falconcare.local',
            'doctor123',
            ['ROLE_DOCTOR', 'ROLE_USER']
        );
        $headers = self::getAuthHeadersFor($client, 'doctor-too-large@test.falconcare.local', 'doctor123');
        $client->request('PUT', '/api/users/' . $doctor->getId(), [], [], $headers, json_encode([
            'profile_image' => str_repeat('A', 2_000_001),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Validation failed', $payload['error']);
    }

    public function testUpdateOwnUserProfileImageNullClearsColumn(): void
    {
        $client = static::createClient();
        $doctor = self::ensureUserExists(
            $client,
            'doctor-clear-img@test.falconcare.local',
            'doctor123',
            ['ROLE_DOCTOR', 'ROLE_USER']
        );
        $doctor->setProfileImage('data:image/png;base64,KEEP');
        $client->getContainer()->get(EntityManagerInterface::class)->flush();

        $headers = self::getAuthHeadersFor($client, 'doctor-clear-img@test.falconcare.local', 'doctor123');
        $client->request('PUT', '/api/users/' . $doctor->getId(), [], [], $headers, json_encode([
            'profile_image' => null,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNull($payload['profile_image']);
        self::assertNull($payload['profile_image_url']);
        self::assertNull($payload['profileImageUrl']);

        $reloaded = $client->getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->find($doctor->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertNull($reloaded->getProfileImage());
    }

    public function testUpdateOwnUserOnlyProfileImagePreservesEmail(): void
    {
        $client = static::createClient();
        $email = 'doctor-preserve-email@test.falconcare.local';
        $doctor = self::ensureUserExists(
            $client,
            $email,
            'doctor123',
            ['ROLE_DOCTOR', 'ROLE_USER']
        );
        $headers = self::getAuthHeadersFor($client, $email, 'doctor123');
        $client->request('PUT', '/api/users/' . $doctor->getId(), [], [], $headers, json_encode([
            'profile_image' => 'data:image/jpeg;base64,XYZ',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $reloaded = $client->getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->find($doctor->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame(mb_strtolower(trim($email)), $reloaded->getEmail());
        self::assertContains('ROLE_DOCTOR', $reloaded->getRoles());
    }

    public function testUpdateOwnUserProfileImageInvalidDataUrlReturns400(): void
    {
        $client = static::createClient();
        $doctor = self::ensureUserExists(
            $client,
            'doctor-bad-dataurl@test.falconcare.local',
            'doctor123',
            ['ROLE_DOCTOR', 'ROLE_USER']
        );
        $headers = self::getAuthHeadersFor($client, 'doctor-bad-dataurl@test.falconcare.local', 'doctor123');
        $client->request('PUT', '/api/users/' . $doctor->getId(), [], [], $headers, json_encode([
            'profile_image' => 'https://example.com/x.png',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Validation failed', $payload['error']);
    }
}
