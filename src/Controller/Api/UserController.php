<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Util\PatientProfileImageResolver;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
#[OA\Tag(name: 'Users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * List all users (doctors, admins, staff).
     */
    #[Route(name: 'api_user_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users',
        summary: 'List users',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User list',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'email', type: 'string', example: 'doctor@example.com'),
                            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Forbidden', 'message' => 'Only admins can list users.'], Response::HTTP_FORBIDDEN);
        }

        $users = $this->userRepository->findBy([], ['id' => 'ASC']);
        $data = array_map(fn (User $user) => $this->serializeUser($user), $users);

        return $this->json($data, Response::HTTP_OK);
    }

    /**
     * Get one user by id.
     */
    #[Route('/{id}', name: 'api_user_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Get user',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'User'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        if (!$this->canAccessUser($user)) {
            return $this->json(['error' => 'Forbidden', 'message' => 'You can only access your own user profile.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serializeUser($user), Response::HTTP_OK);
    }

    /**
     * Create a new user.
     */
    #[Route(name: 'api_user_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        summary: 'Create user',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'plainPassword'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'plainPassword', type: 'string', format: 'password', minLength: 6),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Forbidden', 'message' => 'Only admins can create users.'], Response::HTTP_FORBIDDEN);
        }

        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['require_password' => true, 'csrf_protection' => false]);
        $requestData = self::getRequestData($request);
        self::applyProfileImageAliases($requestData);
        if (($response = self::normalizeProfileImageInRequest($requestData)) instanceof JsonResponse) {
            return $response;
        }
        $form->submit($requestData);

        if (!$form->isValid()) {
            return $this->validationErrorResponse($form);
        }

        $plainPassword = $form->get('plainPassword')->getData();
        if ($plainPassword !== null) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json($this->serializeUser($user), Response::HTTP_CREATED);
    }

    /**
     * Update an existing user.
     */
    #[Route('/{id}', name: 'api_user_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'Update user',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'plainPassword', type: 'string', format: 'password', minLength: 6),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function update(Request $request, User $user): JsonResponse
    {
        if (!$this->canAccessUser($user)) {
            return $this->json(['error' => 'Forbidden', 'message' => 'You can only update your own user profile.'], Response::HTTP_FORBIDDEN);
        }

        $requestData = self::getRequestData($request);
        self::applyProfileImageAliases($requestData);
        if (($response = self::normalizeProfileImageInRequest($requestData)) instanceof JsonResponse) {
            return $response;
        }

        $form = $this->createForm(UserType::class, $user, ['require_password' => false, 'csrf_protection' => false]);
        $form->submit($requestData, false);

        if (!$form->isValid()) {
            return $this->validationErrorResponse($form);
        }

        $plainPassword = $form->get('plainPassword')->getData();
        if ($plainPassword !== null && $plainPassword !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }

        $this->entityManager->flush();

        return $this->json($this->serializeUser($user), Response::HTTP_OK);
    }

    /**
     * Delete a user.
     */
    #[Route('/{id}', name: 'api_user_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/users/{id}',
        summary: 'Delete user',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(User $user): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Forbidden', 'message' => 'Only admins can delete users.'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private static function getRequestData(Request $request): array
    {
        if ($request->getContentTypeFormat() === 'json') {
            return $request->toArray();
        }

        return $request->request->all();
    }

    private function validationErrorResponse(\Symfony\Component\Form\FormInterface $form): JsonResponse
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $errors[] = [
                'field' => $origin instanceof \Symfony\Component\Form\FormInterface ? $origin->getName() : null,
                'message' => $error->getMessage(),
            ];
        }

        return new JsonResponse([
            'error' => 'Validation failed',
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private static function applyProfileImageAliases(array &$requestData): void
    {
        if (array_key_exists('profile_image', $requestData)) {
            $requestData['profileImage'] = $requestData['profile_image'];
            unset($requestData['profile_image'], $requestData['profile_image_url'], $requestData['profileImageUrl']);

            return;
        }

        if (array_key_exists('profile_image_url', $requestData)) {
            $requestData['profileImage'] = $requestData['profile_image_url'];
            unset($requestData['profile_image'], $requestData['profile_image_url'], $requestData['profileImageUrl']);

            return;
        }

        if (array_key_exists('profileImageUrl', $requestData)) {
            $requestData['profileImage'] = $requestData['profileImageUrl'];
            unset($requestData['profile_image'], $requestData['profile_image_url'], $requestData['profileImageUrl']);
        }
    }

    /**
     * Validates and normalizes `profileImage` in place (empty string → null) when present.
     */
    private static function normalizeProfileImageInRequest(array &$requestData): ?JsonResponse
    {
        if (!array_key_exists('profileImage', $requestData)) {
            return null;
        }

        $result = PatientProfileImageResolver::validateAndNormalize($requestData['profileImage']);
        if (!$result['ok']) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'message' => $result['message'],
            ], Response::HTTP_BAD_REQUEST);
        }
        $requestData['profileImage'] = $result['value'];

        return null;
    }

    private function canAccessUser(User $targetUser): bool
    {
        $current = $this->getUser();
        if (!$current instanceof User) {
            return false;
        }

        return $this->isGranted('ROLE_ADMIN') || $current->getId() === $targetUser->getId();
    }

    private function serializeUser(User $user): array
    {
        $profileImage = PatientProfileImageResolver::normalizeForApi($user->getProfileImage());

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'profile_image' => $profileImage,
            'profile_image_url' => $profileImage,
            'profileImageUrl' => $profileImage,
        ];
    }
}
