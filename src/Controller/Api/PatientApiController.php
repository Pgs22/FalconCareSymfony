<?php

namespace App\Controller\Api;

use App\Entity\Patient;
use App\Entity\User;
use App\Repository\PatientRepository;
use App\Repository\UserRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/patients')]
#[OA\Tag(name: 'Patients')]
final class PatientApiController extends AbstractController
{
    #[Route('', name: 'api_patient_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/patients',
        summary: 'List patients',
        //security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Patient list')]
    )]
    public function list(Request $request, PatientRepository $repo): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $patients = $search !== '' ? $repo->findByName($search) : $repo->getAll();

        $data = array_map(static fn (Patient $patient) => self::serializePatient($patient), $patients);

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_patient_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/patients/{id}',
        summary: 'Get patient',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Patient'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $id, PatientRepository $repo): JsonResponse
    {
        $patient = $repo->findById($id);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(self::serializePatient($patient), Response::HTTP_OK);
    }

    #[Route('/by-identity/{identityDocument}', name: 'api_patient_by_identity', methods: ['GET'])]
    #[OA\Get(
        path: '/api/patients/by-identity/{identityDocument}',
        summary: 'Find patients by identity document',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'identityDocument', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Patient(s)'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function byIdentity(string $identityDocument, PatientRepository $repo): JsonResponse
    {
        $patients = $repo->findByIdentityDocument($identityDocument);
        if (empty($patients)) {
            return $this->json(['message' => 'No patients found'], Response::HTTP_NOT_FOUND);
        }

        $data = array_map(static fn (Patient $patient) => self::serializePatient($patient), $patients);

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('', name: 'api_patient_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/patients',
        summary: 'Create patient',
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 400, description: 'Bad request'),
        ]
    )]
    public function create(
        Request $request,
        PatientRepository $repo,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        $data = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();

        $required = ['identityDocument', 'firstName', 'lastName', 'phone', 'email', 'address', 'consultationReason', 'familyHistory', 'healthStatus', 'lifestyleHabits', 'medicationAllergies'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                return $this->json(['message' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
            }
        }

        $identityDocument = (string) $data['identityDocument'];
        if ($repo->findOneByIdentityDocument($identityDocument)) {
            return $this->json(['message' => 'Patient already exists'], Response::HTTP_BAD_REQUEST);
        }

        $email = (string) $data['email'];
        if ($userRepository->findOneByEmailCaseInsensitive($email)) {
            return $this->json(['message' => 'Email already registered'], Response::HTTP_BAD_REQUEST);
        }

        $patient = new Patient();
        $patient->setIdentityDocument($identityDocument);
        $patient->setFirstName((string) $data['firstName']);
        $patient->setLastName((string) $data['lastName']);
        $patient->setSsNumber(isset($data['ssNumber']) ? (string) $data['ssNumber'] : null);
        $patient->setPhone((string) $data['phone']);
        $patient->setEmail($email);
        $patient->setAddress((string) $data['address']);
        $patient->setConsultationReason((string) $data['consultationReason']);
        $patient->setFamilyHistory((string) $data['familyHistory']);
        $patient->setHealthStatus((string) $data['healthStatus']);
        $patient->setLifestyleHabits((string) $data['lifestyleHabits']);
        $patient->setMedicationAllergies((string) $data['medicationAllergies']);

        if (!empty($data['registrationDate'])) {
            try {
                $patient->setRegistrationDate(new \DateTimeImmutable((string) $data['registrationDate']));
            } catch (\Throwable) {
                return $this->json(['message' => 'Invalid registrationDate format'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            $patient->setRegistrationDate(new \DateTimeImmutable());
        }

        $repo->create($patient);

        $plainPassword = (string) ($data['plainPassword'] ?? '');
        if (trim($plainPassword) !== '') {
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $entityManager = $repo->getEntityManager();
            $entityManager->persist($user);
            $entityManager->flush();
        }

        return $this->json(self::serializePatient($patient), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_patient_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[OA\Put(
        path: '/api/patients/{id}',
        summary: 'Update patient',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(Request $request, int $id, PatientRepository $repo): JsonResponse
    {
        $patient = $repo->findById($id);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();

        if (array_key_exists('identityDocument', $data)) {
            $patient->setIdentityDocument((string) $data['identityDocument']);
        }
        if (array_key_exists('firstName', $data)) {
            $patient->setFirstName((string) $data['firstName']);
        }
        if (array_key_exists('lastName', $data)) {
            $patient->setLastName((string) $data['lastName']);
        }
        if (array_key_exists('ssNumber', $data)) {
            $patient->setSsNumber($data['ssNumber'] !== null ? (string) $data['ssNumber'] : null);
        }
        if (array_key_exists('phone', $data)) {
            $patient->setPhone((string) $data['phone']);
        }
        if (array_key_exists('email', $data)) {
            $patient->setEmail((string) $data['email']);
        }
        if (array_key_exists('address', $data)) {
            $patient->setAddress((string) $data['address']);
        }
        if (array_key_exists('consultationReason', $data)) {
            $patient->setConsultationReason((string) $data['consultationReason']);
        }
        if (array_key_exists('familyHistory', $data)) {
            $patient->setFamilyHistory((string) $data['familyHistory']);
        }
        if (array_key_exists('healthStatus', $data)) {
            $patient->setHealthStatus((string) $data['healthStatus']);
        }
        if (array_key_exists('lifestyleHabits', $data)) {
            $patient->setLifestyleHabits((string) $data['lifestyleHabits']);
        }
        if (array_key_exists('medicationAllergies', $data)) {
            $patient->setMedicationAllergies((string) $data['medicationAllergies']);
        }

        if (!empty($data['registrationDate'])) {
            try {
                $patient->setRegistrationDate(new \DateTimeImmutable((string) $data['registrationDate']));
            } catch (\Throwable) {
                return $this->json(['message' => 'Invalid registrationDate format'], Response::HTTP_BAD_REQUEST);
            }
        }

        $repo->edit($patient);

        return $this->json(self::serializePatient($patient), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_patient_delete', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/patients/{id}',
        summary: 'Delete patient',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(int $id, PatientRepository $repo): JsonResponse
    {
        $patient = $repo->findById($id);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $repo->delete($patient);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private static function serializePatient(Patient $patient): array
    {
        return [
            'id' => $patient->getId(),
            'identityDocument' => $patient->getIdentityDocument(),
            'firstName' => $patient->getFirstName(),
            'lastName' => $patient->getLastName(),
            'ssNumber' => $patient->getSsNumber(),
            'phone' => $patient->getPhone(),
            'email' => $patient->getEmail(),
            'address' => $patient->getAddress(),
            'consultationReason' => $patient->getConsultationReason(),
            'familyHistory' => $patient->getFamilyHistory(),
            'healthStatus' => $patient->getHealthStatus(),
            'lifestyleHabits' => $patient->getLifestyleHabits(),
            'registrationDate' => $patient->getRegistrationDate()?->format(DATE_ATOM),
            'medicationAllergies' => $patient->getMedicationAllergies(),
        ];
    }
}

