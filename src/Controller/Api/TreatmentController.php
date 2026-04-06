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
#[Route('/new', name: 'api_treatment_new', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, PathologyRepository $pathRepo): JsonResponse 
    {
        $data = json_decode($request->getContent(), true);
        $treatment = new Treatment();
        $treatment->setTreatmentName($data['name'] ?? 'Nuevo Plan');

        if (!empty($data['pathology_ids'])) {
            foreach ($data['pathology_ids'] as $id) {
                $path = $pathRepo->find($id);
                if ($path) {
                    $treatment->addPathology($path);
                }
            }
        }

        $em->persist($treatment);
        $em->flush();

        return $this->json(['id' => $treatment->getId()]);
    }


    #[Route('/patient/{id}', name: 'api_treatment_by_patient', methods: ['GET'])]
    public function getByPatient(
        int $id, 
        PatientRepository $patientRepo, 
        TreatmentRepository $treatmentRepo
    ): JsonResponse {
        // 1. Buscamos el paciente
        $patient = $patientRepo->find($id);
        if (!$patient) {
            return $this->json(['error' => 'Pacient no trobat'], 404);
        }

        $treatments = $treatmentRepo->findBy(['patient' => $patient]);
        
        $data = [];
        foreach ($treatments as $t) {
            if ($t->getStatus() === 'Actiu') {
                
                $pathologiesCollection = $t->getPathologies(); 
                $firstPathology = $pathologiesCollection->first() ?: null; 

                $pathologyType = $firstPathology ? $firstPathology->getPathologyType() : null;

                $data[] = [
                    'treatmentId'       => $t->getId(),
                    'treatmentName'     => $t->getTreatmentName(), 
                    'pathologyId'       => $firstPathology ? $firstPathology->getId() : null,
                    'pathologyTypeName' => $pathologyType ? $pathologyType->getName() : 'Sense tipus',
                    'duration'          => $pathologyType ? $pathologyType->getDefaultDuration() : 30
                ];
            }
        }

        return $this->json($data);
    }
}
