<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/appointment')]
final class AppointmentController extends AbstractController
{
    #[Route(name: 'app_appointment_index', methods: ['GET'])]
    public function index(AppointmentRepository $appointmentRepository): Response
    {
        return $this->render('appointment/index.html.twig', [
            'appointments' => $appointmentRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_appointment_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, AppointmentRepository $repository): JsonResponse
    {
        $appointment = new Appointment();
        $appointment->setStatus('Scheduled');

        $data = json_decode($request->getContent(), true);

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->submit($data); 

        if ($form->isSubmitted() && $form->isValid()) {
            
            $busy = $repository->findOneBy([ 
                'visitDate' => $appointment->getVisitDate(),
                'visitTime' => $appointment->getVisitTime(),
                'box' => $appointment->getBox()
            ]);

            if ($busy) {
                return $this->json(['error' => 'El Box ya está ocupado en esa hora'], Response::HTTP_CONFLICT);
            }

            $entityManager->persist($appointment);
            $entityManager->flush();

            return $this->json([
                'id' => $appointment->getId(),
                'duration' => $appointment->getDurationMinutes(),
                'message' => 'Cita creada con éxito'
            ], Response::HTTP_CREATED);
        }

        return $this->json(['errors' => 'Datos inválidos'], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{id}/open', name: 'app_appointment_open', methods: ['GET'])]
    public function open(Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        $patient = $appointment->getPatient();

        $odontogramId = $this->PatientController->getLastIdOdontogram($patient);

        if (!$odontogramId) {
            $odontogramId = $this->OdontogramController->createNewVisitOdontogram($patient, $appointment);

            $this->PatientController->saveLastIdOdontogram($patient, $odontogramId);
            
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_odontogram_view', [
            'id' => $odontogramId
        ]);
    }

    #[Route('/{id}', name: 'app_appointment_show', methods: ['GET'])]
    public function show(Appointment $appointment): Response
    {
        return $this->render('appointment/show.html.twig', [
            'appointment' => $appointment,
        ]);
    }

    #[Route('/{id}/update', name: 'app_appointment_update', methods: ['POST', 'PUT'])]
    public function update(
        Request $request, 
        Appointment $appointment, 
        EntityManagerInterface $entityManager,
        AppointmentRepository $repository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createForm(AppointmentType::class, $appointment, ['csrf_protection' => false]);
        
        $form->submit($data, false);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $busy = $repository->createQueryBuilder('a')
                ->where('a.visitDate = :date')
                ->andWhere('a.visitTime = :time')
                ->andWhere('a.box = :box')
                ->andWhere('a.id != :currentId') // Que no sea la propia cita que editamos
                ->setParameter('date', $appointment->getVisitDate())
                ->setParameter('time', $appointment->getVisitTime())
                ->setParameter('box', $appointment->getBox())
                ->setParameter('currentId', $appointment->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($busy) {
                return $this->json(['error' => 'No se puede mover la cita: el Box ya está ocupado'], Response::HTTP_CONFLICT);
            }

            $entityManager->flush();

            return $this->json([
                'status' => 'updated',
                'id' => $appointment->getId(),
                'duration' => $appointment->getDurationMinutes(),
                'message' => 'Cita actualizada correctamente'
            ]);
        }

        return $this->json([
            'error' => 'Error de validación',
            'details' => (string) $form->getErrors(true)
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{id}', name: 'app_appointment_delete', methods: ['POST'])]
    public function delete(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$appointment->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($appointment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_appointment_index', [], Response::HTTP_SEE_OTHER);
    }
}
