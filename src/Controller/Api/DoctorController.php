<?php

namespace App\Controller\Api;

use App\Repository\DoctorRepository;
use App\Repository\BoxRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/appointment')]
final class DoctorController extends AbstractController
{
    /**
     * Este endpoint devuelve los recursos físicos (Boxes) y humanos (Doctores)
     * disponibles para una fecha concreta.
     */
    #[Route('/setup-appointment-form', name: 'app_api_setup_data', methods: ['GET'])]
    public function getSetup(
        Request $request,
        DoctorRepository $docRepo, 
        BoxRepository $boxRepo
    ): JsonResponse {
        
        // 1. Obtener la fecha de la consulta (por defecto hoy)
        $dateStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        try {
            $date = new \DateTime($dateStr);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Fecha inválida'], 400);
        }

        // 2. Determinar el día de la semana en catalán (coincidiendo con tu base de datos)
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

        // 3. Filtrar Doctores: Solo los que trabajan ese día de la semana
        // Nota: findBy(['workDay' => ...]) asume que tu columna se llama workDay
        $allDoctors = $docRepo->findAll();
        $availableDoctors = [];

        foreach ($allDoctors as $doc) {
            // Suponiendo que en tu entidad Doctor guardas el día en minúsculas
            // O puedes crear un método personalizado en el Repository para filtrar por SQL
            if (str_contains(strtolower($doc->getAssignedWeekday() ?? ''), $diaDeLaCita)) {
                $availableDoctors[] = [
                    'id' => $doc->getId(),
                    'name' => $doc->getFirstName() . ' ' . $doc->getLastNames()
                ];
            }
        }

        // 4. Filtrar Boxes: Solo los que están activos (status = true)
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
            'dayDetected' => $diaDeLaCita // Útil para debugear en Angular
        ]);
    }

    /**
     * Listado general de doctores para administración
     */
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
}