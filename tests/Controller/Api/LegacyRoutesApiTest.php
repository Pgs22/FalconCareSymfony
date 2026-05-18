<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LegacyRoutesApiTest extends WebTestCase
{
    /**
     * @return array<string, string>
     */
    private static function authHeaders(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email = 'legacy-routes@test.falconcare.local'): array
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user === null) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($hasher->hashPassword($user, 'secret123'));
            $user->setRoles(['ROLE_ADMIN']);
            $em->persist($user);
            $em->flush();
        }

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'secret123',
        ], \JSON_THROW_ON_ERROR));

        $login = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $login['accessToken'],
        ];
    }

    public function testAppointmentsPluralListByPatientId(): void
    {
        $client = static::createClient();
        $headers = self::authHeaders($client);

        $client->request('GET', '/api/appointments', ['patientId' => '1'], [], $headers);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
    }

    public function testAppointmentIndexFilterByPatientId(): void
    {
        $client = static::createClient();
        $headers = self::authHeaders($client);

        $client->request('GET', '/api/appointment/index', ['patientId' => '1'], [], $headers);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
    }

    public function testAppointmentCreateFallbackRouteExists(): void
    {
        $client = static::createClient();
        $headers = self::authHeaders($client);

        $client->request('POST', '/api/appointment', [], [], $headers, json_encode([
            'visitDate' => null,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
