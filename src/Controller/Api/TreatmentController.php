<?php

namespace App\Controller\Api;

use App\Entity\Treatment;
use App\Repository\TreatmentRepository;
use App\Repository\AppointmentRepository;
use App\Repository\PathologyRepository;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;   

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[Route('/api/treatments')]
final class TreatmentController extends AbstractController
{
    /**
     * CREATE: Crea un nuevo tratamiento y lo vincula a la cita abierta en el odontograma.
     */
    #[Route('/create', name: 'api_treatment_create', methods: ['POST'])]
    public function create(
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
        $treatment->setTreatmentName($data['treatmentName'] ?? 'Nou Pla de Tractament');
        $treatment->setDescription($data['description'] ?? 'Creat des de l\'odontograma');
        $treatment->setStatus($data['status'] ?? 'Actiu');
        $treatment->setEstimatedDuration((int)($data['estimatedDuration'] ?? 30));
        $treatment->setSchedulingNotes($data['schedulingNotes'] ?? null);

        if (!empty($data['pathology_ids']) && is_array($data['pathology_ids'])) {
            foreach ($data['pathology_ids'] as $id) {
                $path = $pathRepo->find((int)$id);
                if ($path) {
                    $treatment->addPathology($path);
                }
            }
        }

        $em->persist($treatment);

        // 3. VINCULACIÓN CON LA CITA (Guarda el id del tratamiento en la agenda)
        if (!empty($data['appointment_id'])) {
            $appointment = $appRepo->find((int)$data['appointment_id']);
            if ($appointment) {
                // Al hacer setTreatment, la tabla 'appointment' guardará el ID de este tratamiento
                $appointment->setTreatment($treatment);

                
                $em->persist($appointment);
            }
        }

        $em->flush();

        return $this->json([
            'id' => $treatment->getId(),
            'status' => 'success',
            'message' => 'Tractament creat i vinculat a la cita correctament'
        ]);
    }

    #[Route('/{id}', name: 'api_treatment_read', methods: ['GET'])]
    public function read(int $id, TreatmentRepository $treatmentRepository): JsonResponse
    {
        $treatment = $treatmentRepository->find($id);

        if (!$treatment) {
            return $this->json(['error' => 'Tractament no trobat'], 404);
        }

        return $this->json([
            'id' => $treatment->getId(),
            'treatmentName' => $treatment->getTreatmentName(),
            'description' => $treatment->getDescription(),
            'status' => $treatment->getStatus(),
            'estimatedDuration' => $treatment->getEstimatedDuration(),
            'schedulingNotes' => $treatment->getSchedulingNotes()
        ]);
    }

    #[Route('/{id}', name: 'treatment_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, TreatmentRepository $treatmentRepository, EntityManagerInterface $em): JsonResponse
    {
        $treatment = $treatmentRepository->find($id);

        if (!$treatment) {
            return $this->json(['error' => 'Tractament no trobat'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['treatmentName'])) {
            $treatment->setTreatmentName($data['treatmentName']);
        }
        if (isset($data['description'])) {
            $treatment->setDescription($data['description']);
        }
        if (isset($data['estimatedDuration'])) {
            $treatment->setEstimatedDuration((int) $data['estimatedDuration']);
        }
        if (isset($data['status'])) {
            $treatment->setStatus($data['status']);
        }
        if (isset($data['schedulingNotes'])) {
            $treatment->setSchedulingNotes($data['schedulingNotes']);
        }

        $em->flush();

        return $this->json([
            'message' => 'Tractament actualitzat correctament',
            'id' => $treatment->getId(),
            'status' => 'success'
        ]);
    }

    #[Route('/{id}', name: 'treatment_delete', methods: ['DELETE'])]
    public function delete(int $id, TreatmentRepository $treatmentRepository, EntityManagerInterface $em): JsonResponse
    {
        $treatment = $treatmentRepository->find($id);

        if (!$treatment) {
            return $this->json(['error' => 'Tractament no trobat'], 404);
        }
        
        $em->remove($treatment);
        $em->flush();

        return $this->json([
            'message' => 'Tractament eliminat correctament',
            'status' => 'success'
        ]);
    }

    #[Route('/patient/{id}', name: 'api_treatment_by_patient', methods: ['GET'])]
    public function getByPatient(int $id, TreatmentRepository $treatmentRepo): JsonResponse 
    {
        $treatments = $treatmentRepo->findActiveTreatmentsByPatient($id);
        
        $data = array_map(function(Treatment $t) {
            
            $pathologies = $t->getPathologies();
            $firstPathology = $pathologies->count() > 0 ? $pathologies->first() : null;
            $pathType = $firstPathology ? $firstPathology->getPathologyType() : null;

            return [
                'treatmentId'       => $t->getId(),
                'treatmentName'     => $t->getTreatmentName(),
                'status_real'       => $t->getStatus(),
                'pathologyId'       => $firstPathology ? $firstPathology->getId() : null,
                'pathologyTypeName' => $pathType ? $pathType->getName() : 'Sense tipus',
                'duration'          => $t->getEstimatedDuration() ?? ($pathType ? $pathType->getDefaultDuration() : 30)
            ];
        }, $treatments);

        return $this->json($data);
    }

    #[Route('/{id}/finish', name: 'api_treatment_finish', methods: ['POST', 'PATCH'])]
    public function finish(int $id, TreatmentRepository $treatmentRepo): JsonResponse
    {
        $rowsUpdated = $treatmentRepo->markAsFinished($id);

        if ($rowsUpdated === 0) {
            return $this->json(['error' => 'No se ha encontrado el tratamiento o ya estaba finalizado'], 404);
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Tractament marcat com a Finalitzat'
        ]);
    }
}
