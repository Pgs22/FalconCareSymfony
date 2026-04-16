<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Doctor;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use App\Util\AppointmentHistoryPayloadBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Colección REST de citas por paciente (JSON plano o Hydra).
 * Convención de filtros (el cliente puede probar en cadena):
 * - ?patientId=1
 * - ?patient.id=1
 * - ?patient=/api/patients/1
 */
#[Route('/api/appointments')]
#[OA\Tag(name: 'Appointments')]
final class AppointmentsApiController extends AbstractController
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly PatientRepository $patientRepository,
        private readonly DoctorRepository $doctorRepository,
    ) {
    }

    #[Route('', name: 'api_appointments_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        path: '/api/appointments',
        summary: 'List appointments for a patient (filtered)',
        description: 'Requires patientId, patient.id, or patient (IRI). ROLE_ADMIN: all; ROLE_DOCTOR/STAFF: own appointments. JSON-LD: Accept application/ld+json or format=jsonld. Angular: GET /api/appointments?patientId=<id> (or patient.id / patient IRI).',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'patientId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patient.id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(
                name: 'patient',
                in: 'query',
                required: false,
                description: 'IRI e.g. /api/patients/12',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'format',
                in: 'query',
                required: false,
                description: 'Use jsonld with Accept application/ld+json for Hydra collection',
                schema: new OA\Schema(type: 'string', enum: ['jsonld'])
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Array of appointment objects or hydra:Collection'),
            new OA\Response(response: 400, description: 'Missing patient filter'),
            new OA\Response(response: 403, description: 'Not allowed for this role / no doctor profile'),
            new OA\Response(response: 404, description: 'Patient not found'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $restrictedDoctorId = $this->resolveRestrictedDoctorId($user);
        if ($restrictedDoctorId === false) {
            return $this->json([
                'error' => 'Forbidden',
                'message' => 'Listing appointments requires ROLE_ADMIN or a doctor profile linked to your account.',
            ], Response::HTTP_FORBIDDEN);
        }

        $patientId = $this->resolvePatientIdFromQuery($request);
        if ($patientId === null || $patientId <= 0) {
            return $this->json([
                'error' => 'Bad Request',
                'message' => 'Provide a patient filter: patientId, patient.id, or patient=/api/patients/{id}.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $patient = $this->patientRepository->findById($patientId);
        if ($patient === null) {
            return $this->json(['error' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $appointments = $this->appointmentRepository->findByPatientId($patientId, $restrictedDoctorId);
        $payload = AppointmentHistoryPayloadBuilder::buildList($appointments);

        if ($this->wantsJsonLd($request)) {
            return $this->json([
                '@context' => '/api/contexts/Appointment',
                '@type' => 'hydra:Collection',
                'hydra:member' => $payload,
                'hydra:totalItems' => \count($payload),
            ], Response::HTTP_OK, [
                'Content-Type' => 'application/ld+json',
            ]);
        }

        return $this->json($payload, Response::HTTP_OK);
    }

    /**
     * @return int|null Patient id or null if not provided / not parseable
     */
    private function resolvePatientIdFromQuery(Request $request): ?int
    {
        $q = $request->query;
        if ($q->has('patientId')) {
            $v = $q->get('patientId');
            if ($v === '' || $v === null) {
                return null;
            }

            return (int) $v;
        }

        if ($q->has('patient.id')) {
            $v = $q->get('patient.id');
            if ($v === '' || $v === null) {
                return null;
            }

            return (int) $v;
        }

        if ($q->has('patient')) {
            return $this->parsePatientIri((string) $q->get('patient'));
        }

        return null;
    }

    private function parsePatientIri(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('#/api/patients/(\d+)(?:\s|$)#', $value, $m)) {
            return (int) $m[1];
        }
        if (preg_match('#^(\d+)$#', $value, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @return int|null Restrict to this doctor id; null = admin (no restriction)
     * @return false User cannot list
     */
    private function resolveRestrictedDoctorId(User $user): int|false|null
    {
        $roles = $user->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return null;
        }

        if (!\in_array('ROLE_DOCTOR', $roles, true) && !\in_array('ROLE_STAFF', $roles, true)) {
            return false;
        }

        $doctor = $this->doctorRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        if (!$doctor instanceof Doctor) {
            return false;
        }

        $id = $doctor->getId();

        return $id ?? false;
    }

    private function wantsJsonLd(Request $request): bool
    {
        if ($request->query->get('format') === 'jsonld') {
            return true;
        }

        $accept = (string) $request->headers->get('Accept', '');
        if (str_contains($accept, 'application/ld+json')) {
            return true;
        }

        return false;
    }
}
