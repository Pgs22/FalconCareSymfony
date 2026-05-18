<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthApiTest extends WebTestCase
{
    public function testLoginReturnsCamelCaseAndSnakeCaseTokenFields(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $email = 'auth-login-shape@test.falconcare.local';
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user === null) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($hasher->hashPassword($user, 'secret123'));
            $user->setRoles(['ROLE_DOCTOR']);
            $em->persist($user);
            $em->flush();
        }

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'secret123',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($payload['accessToken']);
        self::assertSame($payload['accessToken'], $payload['access_token']);
        self::assertArrayHasKey('user', $payload);
        self::assertSame($email, $payload['user']['email']);
        self::assertArrayHasKey('roles', $payload['user']);
    }

    public function testPatientsListRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/patients');
        self::assertResponseStatusCodeSame(401);
    }
}
