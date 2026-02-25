<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Repository\PatientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/patient')]
final class PatientController extends AbstractController
{
    #[Route('/index', methods: ['GET'])]
    public function getAll(PatientRepository $repo): JsonResponse
    {
        $patients = $repo->getAll();

        $data = [];

        foreach ($patients as $patient) {
            $data[] = [
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

        return $this->json($data);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function findById(int $id, PatientRepository $repo): JsonResponse
    {
        $patient = $repo->findById($id);

        if (!$patient) {
            return $this->json(
                ['message' => 'Patient not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json([
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
        ]);
    }

    #[Route('/identity/{identityDocument}', methods: ['GET'])]
    public function findByIdentityDocument(string $identityDocument, PatientRepository $repo): JsonResponse
    {
        $patients = $repo->findByIdentityDocument($identityDocument);

        if (empty($patients)) {
            return $this->json(
                ['message' => 'No patients found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = [];

        foreach ($patients as $patient) {
            $data[] = [
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

        return $this->json($data);
    }

    #[Route('/create', methods: ['POST'])]
    public function create(Request $request, PatientRepository $repo): JsonResponse
    {
        $data = $request->toArray();

        $identityDocument = $data['identityDocument'] ?? null;
        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;
        $ssNumber = $data['ssNumber'] ?? null;
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;
        $address = $data['address'] ?? null;
        $consultationReason = $data['consultationReason'] ?? null;
        $familyHistory = $data['familyHistory'] ?? null;
        $healthStatus = $data['healthStatus'] ?? null;
        $lifestyleHabits = $data['lifestyleHabits'] ?? null;
        $medicationAllergies = $data['medicationAllergies'] ?? null;

        if (
            !$identityDocument || !$firstName || !$lastName || !$phone || !$email ||
            !$address || !$consultationReason || !$familyHistory || !$healthStatus ||
            !$lifestyleHabits || !$medicationAllergies
        ) {
            return $this->json(
                ['message' => 'Missing required fields'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($repo->findOneByIdentityDocument($identityDocument)) {
            return $this->json(
                ['message' => 'Patient already exists'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $patient = new Patient();
        $patient->setIdentityDocument($identityDocument);
        $patient->setFirstName($firstName);
        $patient->setLastName($lastName);
        $patient->setSsNumber($ssNumber);
        $patient->setPhone($phone);
        $patient->setEmail($email);
        $patient->setAddress($address);
        $patient->setConsultationReason($consultationReason);
        $patient->setFamilyHistory($familyHistory);
        $patient->setHealthStatus($healthStatus);
        $patient->setLifestyleHabits($lifestyleHabits);
        $patient->setMedicationAllergies($medicationAllergies);

        if (!empty($data['registrationDate'])) {
            try {
                $patient->setRegistrationDate(new \DateTimeImmutable($data['registrationDate']));
            } catch (\Throwable) {
                return $this->json(
                    ['message' => 'Invalid registrationDate format'],
                    Response::HTTP_BAD_REQUEST
                );
            }
        } else {
            $patient->setRegistrationDate(new \DateTimeImmutable());
        }

        $repo->create($patient);

        return $this->json([
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
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function edit(Request $request, int $id, PatientRepository $repo): JsonResponse
    {
        $patient = $repo->findById($id);

        if (!$patient) {
            return $this->json(
                ['message' => 'Patient not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = $request->toArray();

        $patient->setIdentityDocument($data['identityDocument'] ?? $patient->getIdentityDocument());
        $patient->setFirstName($data['firstName'] ?? $patient->getFirstName());
        $patient->setLastName($data['lastName'] ?? $patient->getLastName());
        $patient->setSsNumber(array_key_exists('ssNumber', $data) ? $data['ssNumber'] : $patient->getSsNumber());
        $patient->setPhone($data['phone'] ?? $patient->getPhone());
        $patient->setEmail($data['email'] ?? $patient->getEmail());
        $patient->setAddress($data['address'] ?? $patient->getAddress());
        $patient->setConsultationReason($data['consultationReason'] ?? $patient->getConsultationReason());
        $patient->setFamilyHistory($data['familyHistory'] ?? $patient->getFamilyHistory());
        $patient->setHealthStatus($data['healthStatus'] ?? $patient->getHealthStatus());
        $patient->setLifestyleHabits($data['lifestyleHabits'] ?? $patient->getLifestyleHabits());
        $patient->setMedicationAllergies($data['medicationAllergies'] ?? $patient->getMedicationAllergies());

        if (!empty($data['registrationDate'])) {
            try {
                $patient->setRegistrationDate(new \DateTimeImmutable($data['registrationDate']));
            } catch (\Throwable) {
                return $this->json(
                    ['message' => 'Invalid registrationDate format'],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        $repo->edit($patient);

        return $this->json([
            'message' => 'Patient successfully updated',
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, PatientRepository $repo): JsonResponse
    {
        $patient = $repo->findById($id);

        if (!$patient) {
            return $this->json(
                ['message' => 'Patient not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $repo->delete($patient);

        return $this->json([
            'message' => 'Patient successfully deleted',
        ]);
    }
}