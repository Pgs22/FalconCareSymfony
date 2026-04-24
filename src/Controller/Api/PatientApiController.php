<?php

namespace App\Controller\Api;

use App\Entity\Patient;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Repository\PatientRepository;
use App\Repository\UserRepository;
use App\Repository\AppointmentRepository;
use App\Service\PatientRecordsAccessChecker;
use App\Service\RealtimeSyncPublisher;
use App\Util\DocumentApiSerializer;
use App\Util\PatientMedicationAllergiesResolver;
use App\Util\PatientProfileImageResolver;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Util\AppointmentHistoryPayloadBuilder;

/**
 * JSON paciente:
 * - Alergias: `medicationAllergies` canónico; `medication_allergies` duplicado; POST/PUT aceptan ambas (deben coincidir si vienen las dos).
 * - Allergy bitmask: `allergiesBitmask` and `selectedAllergies`.
 * - Imagen perfil: `profile_image` canónico; `profile_image_url` y `profileImage` compat; prioridad de entrada: profile_image > profile_image_url > profileImage.
 */
#[Route('/api/patients')]
#[OA\Tag(name: 'Patients')]
final class PatientApiController extends AbstractController
{
    public function __construct(
        private readonly PatientRecordsAccessChecker $patientRecordsAccess,
        private readonly RealtimeSyncPublisher $syncPublisher,
    ) {
    }

    #[Route('', name: 'api_patient_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/patients',
        summary: 'List patients',
        //security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Optional filter: first name, last name, full name, and numeric patient id when the term is only digits.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [new OA\Response(response: 200, description: 'Patient list')]
    )]
    public function list(Request $request, PatientRepository $repo): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        try {
            $patients = $search !== '' ? $repo->search($search) : $repo->getAll();
        } catch (\Throwable) {
            $patients = [];
        }

        $data = array_map(static fn (Patient $patient) => self::serializePatient($patient), $patients);

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/{id}/documents', name: 'api_patient_documents_list', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/patients/{id}/documents',
        summary: 'List documents for patient',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Array or hydra:Collection'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Patient not found'),
        ]
    )]
    public function listDocuments(
        int $id,
        Request $request,
        DocumentRepository $documentRepository,
        PatientRepository $repo,
        #[Autowire('%env(API_BASE_URL)%')]
        string $apiBaseUrl,
    ): JsonResponse {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->json(
                ['error' => 'Forbidden', 'message' => 'You do not have permission to list patient documents.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $patient = $repo->findById($id);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $documents = $documentRepository->findByPatientOrdered($patient);
        $members = DocumentApiSerializer::collection($documents, $apiBaseUrl);

        return DocumentApiSerializer::createDocumentListResponse($request, $members);
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Include medicationAllergies and/or medication_allergies (same value if both). Optional allergy bitmask input: allergiesBitmask or selectedAllergies. GET responses expose both allergy text keys and the bitmask fields.',
            content: new OA\JsonContent(type: 'object')
        ),
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

        $required = ['identityDocument', 'firstName', 'lastName', 'phone', 'email', 'address', 'consultationReason', 'familyHistory', 'healthStatus', 'lifestyleHabits'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                return $this->json(['message' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
            }
        }

        $allergiesResolved = PatientMedicationAllergiesResolver::resolveForCreate($data);
        if (!$allergiesResolved['ok']) {
            return $this->json(['message' => $allergiesResolved['message']], Response::HTTP_BAD_REQUEST);
        }

        $allergiesBitmask = $this->resolveAllergiesBitmask($data, $allergiesResolved['value']);

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
        $patient->setMedicationAllergies($allergiesResolved['value']);
        $patient->setAllergiesBitmask($allergiesBitmask);

        if (!empty($data['registrationDate'])) {
            try {
                $patient->setRegistrationDate(new \DateTimeImmutable((string) $data['registrationDate']));
            } catch (\Throwable) {
                return $this->json(['message' => 'Invalid registrationDate format'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            $patient->setRegistrationDate(new \DateTimeImmutable());
        }

        $profilePick = PatientProfileImageResolver::pickFromArray($data);
        if ($profilePick['present'] ?? false) {
            $normalized = PatientProfileImageResolver::validateAndNormalize($profilePick['value']);
            if (!$normalized['ok']) {
                return $this->json(['message' => $normalized['message']], Response::HTTP_BAD_REQUEST);
            }
            $patient->setProfileImage($normalized['value']);
        }

        $repo->create($patient);
        $this->syncPublisher->publishTopic('patients.changed');
        $allergyFieldsPresent = array_key_exists('medicationAllergies', $data)
            || array_key_exists('medication_allergies', $data)
            || array_key_exists('allergiesBitmask', $data)
            || array_key_exists('allergies_bitmask', $data)
            || array_key_exists('selectedAllergies', $data)
            || array_key_exists('selected_allergies', $data);
        if ($allergyFieldsPresent) {
            $this->syncPublisher->publishTopic('allergies.changed');
        }

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

    #[Route('/{id}', name: 'api_patient_update', requirements: ['id' => '\\d+'], methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/patients/{id}',
        summary: 'Update patient (PUT or PATCH; partial body)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Partial JSON: only include fields to update. profile_image: data URL or null to clear. Allergies: medicationAllergies and/or medication_allergies (must match if both sent). Bitmask: allergiesBitmask or selectedAllergies. Requires ROLE_DOCTOR, ROLE_STAFF, or ROLE_ADMIN.',
            content: new OA\JsonContent(type: 'object')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(Request $request, int $id, PatientRepository $repo): JsonResponse
    {
        if (!$this->patientRecordsAccess->canAccessPatientClinicalApi()) {
            return $this->json(
                ['error' => 'Forbidden', 'message' => 'You do not have permission to update patients.'],
                Response::HTTP_FORBIDDEN
            );
        }

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

        $allergiesUpdate = PatientMedicationAllergiesResolver::resolveForPartialUpdate($data);
        if (($allergiesUpdate['apply'] ?? false) === true) {
            if (isset($allergiesUpdate['error'])) {
                return $this->json(['message' => $allergiesUpdate['error']], Response::HTTP_BAD_REQUEST);
            }
            $patient->setMedicationAllergies($allergiesUpdate['value']);
        }

        if (
            array_key_exists('allergiesBitmask', $data)
            || array_key_exists('allergies_bitmask', $data)
            || array_key_exists('selectedAllergies', $data)
            || array_key_exists('selected_allergies', $data)
        ) {
            $patient->setAllergiesBitmask(
                $this->resolveAllergiesBitmask($data, (string) ($patient->getMedicationAllergies() ?? ''))
            );
        }

        $profilePick = PatientProfileImageResolver::pickFromArray($data);
        if ($profilePick['present'] ?? false) {
            $normalized = PatientProfileImageResolver::validateAndNormalize($profilePick['value']);
            if (!$normalized['ok']) {
                return $this->json(['message' => $normalized['message']], Response::HTTP_BAD_REQUEST);
            }
            $patient->setProfileImage($normalized['value']);
        }

        if (!empty($data['registrationDate'])) {
            try {
                $patient->setRegistrationDate(new \DateTimeImmutable((string) $data['registrationDate']));
            } catch (\Throwable) {
                return $this->json(['message' => 'Invalid registrationDate format'], Response::HTTP_BAD_REQUEST);
            }
        }

        $repo->edit($patient);

        $this->syncPublisher->publishTopic('patients.changed');
        $allergyFieldsChanged = array_key_exists('medicationAllergies', $data)
            || array_key_exists('medication_allergies', $data)
            || array_key_exists('allergiesBitmask', $data)
            || array_key_exists('allergies_bitmask', $data)
            || array_key_exists('selected_allergies', $data)
            || array_key_exists('selectedAllergies', $data);
        if ($allergyFieldsChanged) {
            $this->syncPublisher->publishTopic('allergies.changed');
        }

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
        $this->syncPublisher->publishTopic('patients.changed');

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/appointments', name: 'api_patient_appointments', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function appointmentsHistory(int $id, PatientRepository $repo, AppointmentRepository $appointmentRepository): JsonResponse
    {
        $patient = $repo->findById($id);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $appointments = $appointmentRepository->findByPatientId($id);

        return $this->json(AppointmentHistoryPayloadBuilder::buildList($appointments), Response::HTTP_OK);
    }

    private static function serializePatient(Patient $patient): array
    {
        $profile = PatientProfileImageResolver::normalizeForApi($patient->getProfileImage());

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
            'medication_allergies' => $patient->getMedicationAllergies(),
            'allergiesBitmask' => $patient->getAllergiesBitmask(),
            'selectedAllergies' => $patient->getSelectedAllergies(),
            'profile_image' => $profile,
            'profile_image_url' => $profile,
            'profileImage' => $profile,
            'profileImageUrl' => $profile,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveAllergiesBitmask(array $data, string $medicationAllergies = ''): int
    {
        if (array_key_exists('selectedAllergies', $data) && is_array($data['selectedAllergies'])) {
            return Patient::buildAllergiesBitmask($data['selectedAllergies']);
        }

        if (array_key_exists('selected_allergies', $data) && is_array($data['selected_allergies'])) {
            return Patient::buildAllergiesBitmask($data['selected_allergies']);
        }

        if (array_key_exists('allergiesBitmask', $data)) {
            return (int) $data['allergiesBitmask'];
        }

        if (array_key_exists('allergies_bitmask', $data)) {
            return (int) $data['allergies_bitmask'];
        }

        if ($medicationAllergies !== '') {
            return $this->buildBitmaskFromMedicationText($medicationAllergies);
        }

        return 0;
    }

    private function buildBitmaskFromMedicationText(string $medicationAllergies): int
    {
        $lower = mb_strtolower($medicationAllergies);
        $catalog = Patient::getAllergyCatalog();
        $bitmask = 0;

        foreach ($catalog as $flag => $label) {
            if (str_contains($lower, mb_strtolower($label))) {
                $bitmask |= (int) $flag;
            }
        }

        return $bitmask;
    }
}

