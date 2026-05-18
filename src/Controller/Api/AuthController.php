<?php

namespace App\Controller\Api;

use App\Entity\Doctor;
use App\Entity\User;
use App\Repository\DoctorRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Security\JwtTokenManager;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
#[OA\Tag(name: 'Auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly DoctorRepository $doctorRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Login (JWT)',
        description: 'Returns a Bearer JWT to authenticate subsequent API requests.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'doctor@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'securePassword123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'JWT issued',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'accessToken', type: 'string'),
                        new OA\Property(property: 'tokenType', type: 'string', example: 'Bearer'),
                        new OA\Property(property: 'expiresIn', type: 'integer', example: 3600),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'email', type: 'string', example: 'doctor@example.com'),
                                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'))
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $payload = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json([
                'error' => 'Validation failed',
                'errors' => [
                    ['field' => 'email', 'message' => 'This value should not be blank.'],
                    ['field' => 'password', 'message' => 'This value should not be blank.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User|null $user */
        $user = $this->userRepository->findOneByEmailCaseInsensitive($email);
        if (!$user) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtTokenManager->createToken($user);

        return $this->json([
            'accessToken' => $token,
            'tokenType' => 'Bearer',
            'expiresIn' => $this->jwtTokenManager->getTtlSeconds(),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/register-doctor', name: 'api_auth_register_doctor', methods: ['POST'])]
    public function registerDoctor(Request $request): JsonResponse
    {
        $payload = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();

        $fullName = trim((string) ($payload['fullName'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($fullName === '' || $email === '' || $password === '') {
            return $this->json([
                'error' => 'Validation failed',
                'message' => 'fullName, email and password are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($password) < 8) {
            return $this->json([
                'error' => 'Validation failed',
                'message' => 'Password must be at least 8 characters.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $normalizedEmail = mb_strtolower($email);
        if ($this->userRepository->findOneByEmailCaseInsensitive($normalizedEmail)) {
            return $this->json([
                'error' => 'Validation failed',
                'message' => 'Email already registered.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $nameParts = preg_split('/\s+/', $fullName) ?: [];
        if (count($nameParts) < 2) {
            return $this->json([
                'error' => 'Validation failed',
                'message' => 'Full name must include name and surname.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $firstName = (string) array_shift($nameParts);
        $lastNames = trim(implode(' ', $nameParts));

        $user = new User();
        $user->setEmail($normalizedEmail);
        $user->setRoles(['ROLE_DOCTOR']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $doctor = new Doctor();
        $doctor->setFirstName($firstName);
        $doctor->setLastNames($lastNames);
        $doctor->setEmail($normalizedEmail);
        $doctor->setPhone('000000000');
        $doctor->setSpecialty('Sin especialidad asignada');

        $this->entityManager->persist($user);
        $this->entityManager->persist($doctor);
        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'api_auth_delete_me', methods: ['DELETE'])]
    public function deleteCurrentUser(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $doctor = $this->doctorRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        if ($doctor instanceof Doctor) {
            $this->entityManager->remove($doctor);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}

