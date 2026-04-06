<?php

namespace App\Controller\Api;

use App\Entity\Treatment;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\TreatmentRepository;
use App\Repository\PatientRepository;
use App\Repository\PathologyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;   
use Symfony\Component\HttpFoundation\Response;
use App\Repository\AppointmentRepository;

#[Route('/api/treatments')]
final class TreatmentController extends AbstractController
{
    #[Route('/new', name: 'api_treatment_new', methods: ['POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $em, 
        PathologyRepository $pathRepo,
        AppointmentRepository $appRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Dades invàlides'], 400);
        }

        $treatment = new Treatment();
        $treatment->setTreatmentName($data['name'] ?? 'Nou Pla de Tractament');
        $treatment->setStatus('Actiu');

        if (!empty($data['pathology_ids'])) {
            foreach ($data['pathology_ids'] as $id) {
                $path = $pathRepo->find($id);
                if ($path) {
                    $treatment->addPathology($path);
                }
            }
        }

        if (!empty($data['appointment_id'])) {
            $appointment = $appRepo->find($data['appointment_id']);
            if ($appointment) {
                $appointment->setTreatment($treatment);
                $em->persist($appointment);
            }
        }

        $em->persist($treatment);
        $em->flush();

        return $this->json([
            'id' => $treatment->getId(),
            'status' => 'success',
            'message' => 'Tractament creat i vinculat a la cita'
        ]);
    }


    #[Route('/patient/{id}', name: 'api_treatment_by_patient', methods: ['GET'])]
    public function getByPatient(
        int $id, 
        PatientRepository $patientRepo, 
        AppointmentRepository $appointmentRepo
    ): JsonResponse {
        $patient = $patientRepo->find($id);
        if (!$patient) {
            return $this->json(['error' => 'Pacient no trobat'], 404);
        }

        $appointments = $appointmentRepo->findBy(['patient' => $patient]);
        
        $data = [];
        $seenTreatments = [];

        foreach ($appointments as $app) {
            $t = $app->getTreatment();
            
            if ($t && strtolower(trim($t->getStatus())) === 'actiu' && !in_array($t->getId(), $seenTreatments)) {
                
                $seenTreatments[] = $t->getId();
                $firstPathology = $t->getPathologies()->first() ?: null;
                $pathologyType = $firstPathology ? $firstPathology->getPathologyType() : null;
                $dbStatus = $t->getStatus();

                $data[] = [
                    'treatmentId'       => $t->getId(),
                    'treatmentName'     => $t->getTreatmentName(),
                    'status_real'       => $dbStatus,
                    'pathologyId'       => $firstPathology ? $firstPathology->getId() : null,
                    'pathologyTypeName' => $pathologyType ? $pathologyType->getName() : 'Sense tipus',
                    'duration'          => $pathologyType ? $pathologyType->getDefaultDuration() : 30
                ];
            }
        }

        return $this->json($data);
    }
}
