<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for User CRUD API (issue #5).
 * Requires test database with schema applied (php bin/console doctrine:migrations:migrate --env=test).
 */
final class UserControllerTest extends WebTestCase
{
    private static ?User $adminUser = null;

    private static function ensureAdminUserExists(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
    {
        if (self::$adminUser !== null) {
            return;
        }
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $existing = $em->getRepository(User::class)->findOneBy(['email' => 'admin@test.falconcare.local']);
        if ($existing !== null) {
            self::$adminUser = $existing;
            return;
        }

        $admin = new User();
        $admin->setEmail('admin@test.falconcare.local');
        $admin->setPassword($hasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $em->persist($admin);
        $em->flush();
        self::$adminUser = $admin;
    }

    public function testListUsersRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/users');
        // Form login redirects to /login when not authenticated
        self::assertResponseRedirects('/login', Response::HTTP_FOUND);
    }

    public function testListUsersAsAdmin(): void
    {
        $client = static::createClient();
        self::ensureAdminUserExists($client);
        $client->loginUser(self::$adminUser);
        $client->request('GET', '/api/users');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
    }

    public function testCreateUserValidationError(): void
    {
        $client = static::createClient();
        self::ensureAdminUserExists($client);
        $client->loginUser(self::$adminUser);
        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => '',
            'plainPassword' => 'short',
            'roles' => ['ROLE_USER'],
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('error', $data);
        self::assertSame('Validation failed', $data['error']);
    }

    public function testCreateUserSuccess(): void
    {
        $client = static::createClient();
        self::ensureAdminUserExists($client);
        $client->loginUser(self::$adminUser);
        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'newuser-' . uniqid() . '@test.falconcare.local',
            'plainPassword' => 'validpass123',
            'roles' => ['ROLE_USER', 'ROLE_DOCTOR'],
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame('content-type', 'application/json');
        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('email', $data);
        self::assertArrayHasKey('roles', $data);
        self::assertArrayNotHasKey('password', $data);
    }

    public function testShowUser(): void
    {
        $client = static::createClient();
        self::ensureAdminUserExists($client);
        $client->loginUser(self::$adminUser);
        $client->request('GET', '/api/users/1');
        if ($client->getResponse()->getStatusCode() === Response::HTTP_NOT_FOUND) {
            self::markTestSkipped('No user with id 1 in test database');
        }
        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);
        self::assertArrayHasKey('email', $data);
        self::assertArrayNotHasKey('password', $data);
    }

    public function testUpdateUser(): void
    {
        $client = static::createClient();
        self::ensureAdminUserExists($client);
        $client->loginUser(self::$adminUser);
        $client->request('PUT', '/api/users/1', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'updated-' . uniqid() . '@test.falconcare.local',
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
        ]));
        if ($client->getResponse()->getStatusCode() === Response::HTTP_NOT_FOUND) {
            self::markTestSkipped('No user with id 1 in test database');
        }
        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);
        self::assertArrayHasKey('email', $data);
    }

    public function testDeleteUserReturns404WhenNotFound(): void
    {
        $client = static::createClient();
        self::ensureAdminUserExists($client);
        $client->loginUser(self::$adminUser);
        $client->request('DELETE', '/api/users/999999');
        // 404 when user id does not exist
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
