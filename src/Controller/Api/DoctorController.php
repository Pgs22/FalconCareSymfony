<?php

namespace App\Controller\Api;

use App\Repository\DoctorRepository;
use App\Repository\BoxRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/appointment')]
final class DoctorController extends AbstractController
{

    #[Route('/setup-appointment-form', name: 'app_api_setup_data', methods: ['GET'])]
    public function getSetup(
        Request $request,
        DoctorRepository $docRepo, 
        BoxRepository $boxRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        
        $dateStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        try {
            $date = new \DateTime($dateStr);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Fecha inválida'], 400);
        }

        $diasSemana = [
            0 => 'diumenge',
            1 => 'dilluns',
            2 => 'dimarts',
            3 => 'dimecres',
            4 => 'dijous',
            5 => 'divendres',
            6 => 'dissabte'
        ];
        $diaDeLaCita = $diasSemana[(int)$date->format('w')];

        $allDoctors = $docRepo->findAll();
        $availableDoctors = [];

        foreach ($allDoctors as $doc) {
            if (str_contains(strtolower($doc->getAssignedWeekday() ?? ''), $diaDeLaCita)) {
                $availableDoctors[] = [
                    'id' => $doc->getId(),
                    'name' => $doc->getFirstName() . ' ' . $doc->getLastNames()
                ];
            }
        }

        $activeBoxes = $boxRepo->findBy(['status' => true]);
        $boxesData = array_map(function($box) {
            return [
                'id' => $box->getId(),
                'name' => $box->getBoxName()
            ];
        }, $activeBoxes);

        return $this->json([
            'doctors' => $availableDoctors,
            'boxes' => $boxesData,
            'pathologies' => $this->getPathologiesData($em),
            'dayDetected' => $diaDeLaCita
        ]);
    }

    #[Route('/doctors', name: 'app_doctor_list', methods: ['GET'])]
    public function index(DoctorRepository $doctorRepository): JsonResponse
    {
        $doctors = $doctorRepository->findAll();
        $data = array_map(function($doctor) {
            return [
                'id' => $doctor->getId(),
                'fullName' => $doctor->getFirstName() . ' ' . $doctor->getLastNames(),
                'specialty' => $doctor->getSpecialty(),
                'workDay' => $doctor->getAssignedWeekday()
            ];
        }, $doctors);

        return $this->json($data);
    }

    private function getPathologiesData(EntityManagerInterface $em): array
    {
        $pathologyRepo = $em->getRepository(\App\Entity\PathologyType::class);
        $allPathologies = $pathologyRepo->findAll();

        return array_map(function($p) {
            return [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'duration' => $p->getDefaultDuration()
            ];
        }, $allPathologies);
    }
}