<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\DoctorRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\BoxRepository;

#[Route('/api/appointment')]
final class DoctorController extends AbstractController
{
    #[Route('/doctors', name: 'app_doctor_list', methods: ['GET'])]
    public function index(DoctorRepository $doctorRepository): JsonResponse
    {
        $doctors = $doctorRepository->findAll();
        $data = [];

        foreach ($doctors as $doctor) {
            $data[] = [
                'id' => $doctor->getId(),
                'fullName' => $doctor->getFirstName() . ' ' . $doctor->getLastNames(),
                'specialty' => $doctor->getSpecialty(),
            ];
        }

        return $this->json($data);
    }


    #[Route('/setup-appointment-form', name: 'app_api_setup_data', methods: ['GET'])]
    public function getSetup(DoctorRepository $docRepo, BoxRepository $boxRepo): JsonResponse
    {
        $doctors = array_map(function($doc) {
            return [
                'id' => $doc->getId(),
                'name' => $doc->getFirstName() . ' ' . $doc->getLastNames()
            ];
        }, $docRepo->findAll());

        $boxes = array_map(function($box) {
            return [
                'id' => $box->getId(),
                'name' => $box->getBoxName()
            ];
        }, $boxRepo->findAll());

        return $this->json([
            'doctors' => $doctors,
            'boxes' => $boxes,
        ]);
    }
}
