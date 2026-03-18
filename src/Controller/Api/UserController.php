<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/users')]
#[OA\Tag(name: 'Users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly SerializerInterface $serializer,
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
        $users = $this->userRepository->findBy([], ['id' => 'ASC']);
        $data = $this->serializer->serialize($users, 'json', [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['password'],
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
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
        $data = $this->serializer->serialize($user, 'json', [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['password'],
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
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
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['require_password' => true, 'csrf_protection' => false]);
        $form->submit(self::getRequestData($request));

        if (!$form->isValid()) {
            return $this->validationErrorResponse($form);
        }

        $plainPassword = $form->get('plainPassword')->getData();
        if ($plainPassword !== null) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $data = $this->serializer->serialize($user, 'json', [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['password'],
        ]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
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
        $form = $this->createForm(UserType::class, $user, ['require_password' => false, 'csrf_protection' => false]);
        $form->submit(self::getRequestData($request), false);

        if (!$form->isValid()) {
            return $this->validationErrorResponse($form);
        }

        $plainPassword = $form->get('plainPassword')->getData();
        if ($plainPassword !== null && $plainPassword !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }

        $this->entityManager->flush();

        $data = $this->serializer->serialize($user, 'json', [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['password'],
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
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
}
