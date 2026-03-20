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


#[Route('/api/treatments')]
final class Treatmentontroller extends AbstractController
{
    /**
     * Crea un tratamiento y le asigna patologías existentes.
     */
    #[Route('/new', name: 'app_appointment_new', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, PathologyRepository $pathRepo): JsonResponse 
    {
        $data = json_decode($request->getContent(), true);
        $treatment = new Treatment();
        $treatment->setTreatmentName($data['name'] ?? 'Nuevo Plan');

        if (!empty($data['pathology_ids'])) {
            foreach ($data['pathology_ids'] as $id) {
                $path = $pathRepo->find($id);
                if ($path) {
                    // Aquí se dispara la lógica que acabamos de escribir arriba
                    $treatment->addPathology($path);
                }
            }
        }

        $em->persist($treatment);
        $em->flush();

        return $this->json(['id' => $treatment->getId()]);
    }

    #[Route('/patient/{id}', name: 'api_treatment_by_patient', methods: ['GET'])]
    public function getByPatient(int $id, PatientRepository $patientRepo): JsonResponse
    {
        $patient = $patientRepo->find($id);
        if (!$patient) {
            return $this->json(['error' => 'Paciente no encontrado'], 404);
        }

        $treatments = $patient->getTreatments();
        
        $data = [];
        foreach ($treatments as $t) {
            if ($t->getStatus() !== 'COMPLETED') {
                $data[] = [
                    'id' => $t->getId(),
                    'name' => $t->getName(),
                    'status' => $t->getStatus()
                ];
            }
        }

        return $this->json($data);
    }
}
