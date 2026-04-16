<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Entity\Odontogram;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Rutas bajo /api/appointment (calendario, CRUD de cita).
 * Para historial por paciente con filtros tipo API Platform, usar GET {@see \App\Controller\Api\AppointmentsApiController} `/api/appointments`.
 */
#[Route('/api/appointment')]
final class AppointmentController extends AbstractController
{
    #[Route('/index', name: 'app_appointment_index', methods: ['GET'])]
    public function index(Request $request, AppointmentRepository $repo): JsonResponse 
    {
        $fechaStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        $fecha = new \DateTime($fechaStr);

        $appointments = $repo->findByDate($fecha);

        return $this->json($this->serializeAppointments($appointments));
    }

    private function serializeAppointments(array $appointments): array
        {
        $result = [];
        foreach ($appointments as $appointment) {
            $reason = $appointment->getConsultationReason() ?? '';
            $status = $appointment->getStatus() ?? 'Pendent';
            
            $isUrgency = $appointment->isUrgency() || str_contains(strtolower($reason), 'urgència') || str_contains(strtolower($reason), 'urgencia');
            $isFirstVisit = $appointment->isFirstVisit() || str_contains(strtolower($reason), 'primera visita');

            if ($status === 'Finalitzada') {
                $color = '#9e9e9e';
            } elseif ($isUrgency) {
                $color = '#e91e63';
            } elseif ($isFirstVisit) {
                $color = '#9c27b0';
            } else {
                $color = '#00bcd4';
            }

            $result[] = [
                'id' => $appointment->getId(),
                'time' => $appointment->getVisitTime() ? $appointment->getVisitTime()->format('H:i') : '--:--',
                'duration' => $appointment->getDurationMinutes() ?? 30,
                'cleaningTime' => 5,
                'totalBlockTime' => ($appointment->getDurationMinutes() ?? 30) + 5,
                'patientName' => $appointment->getPatient() 
                    ? $appointment->getPatient()->getFirstName() . ' ' . $appointment->getPatient()->getLastName() 
                    : 'Sense Pacient',
                'doctorName' => $appointment->getDoctor() 
                    ? $appointment->getDoctor()->getFirstName() 
                    : 'Sense Doctor',
                'boxId' => $appointment->getBox() ? $appointment->getBox()->getId() : null,
                'box' => $appointment->getBox() ? $appointment->getBox()->getBoxName() : 'Sense Box',
                'reason' => $reason,
                'status' => $status,
                'color' => $color,
                'isUrgency' => $isUrgency,
                'isFirstVisit' => $isFirstVisit
            ];
        }
        return $result;
    }

    #[Route('/weekly', name: 'app_appointment_weekly', methods: ['GET'])]
    public function weekly(Request $request, AppointmentRepository $repo): JsonResponse 
    {
        $fechaStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        try {
            $fecha = new \DateTime($fechaStr);
            $appointments = $repo->findByWeek($fecha);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Data no vàlida'], 400);
        }

        return $this->json($this->serializeAppointments($appointments));
    }

    private function isBoxOccupied(AppointmentRepository $repository, Appointment $newApp): bool 
    {
        $overlaps = $repository->findOverlappingAppointments(
            $newApp->getVisitDate(),
            $newApp->getVisitTime(),
            (int)($newApp->getDurationMinutes() ?? 15),
            $newApp->getBox()?->getId(),
            $newApp->getId()
        );
        return count($overlaps) > 0;
    }

    #[Route('/new', name: 'app_appointment_new', methods: ['POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager, 
        AppointmentRepository $repository
    ): JsonResponse {
        $appointment = new Appointment();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['errors' => 'JSON mal format o buit'], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->submit($data, false); 

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$appointment->getStatus()) {
                    $appointment->setStatus('Programada'); 
                }

                if ($appointment->getObservations() === null) {
                    $appointment->setObservations(''); 
                }

                if (!$appointment->getConsultationReason()) {
                    $appointment->setConsultationReason('Consulta general');
                }

                if (isset($data['durationMinutes'])) {
                    $appointment->setDurationMinutes((int)$data['durationMinutes']);
                }

                if ($this->isBoxOccupied($repository, $appointment)) {
                    return $this->json(['error' => 'Box ocupat'], Response::HTTP_CONFLICT);
                }

                $entityManager->persist($appointment);
                $entityManager->flush(); 

                return $this->json([
                    'id' => $appointment->getId(),
                    'message' => 'Cita creada amb èxit'
                ], Response::HTTP_CREATED);

            } catch (\Exception $e) {
                return $this->json([
                    'errors' => 'Error de base de dades',
                    'debug' => $e->getMessage()
                ], 500);
            }
        }

        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $origin = $error->getOrigin();
            $fieldName = $origin ? $origin->getName() : 'form';
            $errors[] = $fieldName . ': ' . $error->getMessage();
        }

        return $this->json([
            'errors' => 'Dades invàlides',
            'debug' => $errors,
            'received' => $data
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{id}/open', name: 'app_appointment_open', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function open(
        Appointment $appointment, 
        EntityManagerInterface $entityManager
    ): Response {
        $patient = $appointment->getPatient();
        $odontogramId = $patient->getLastOdontogramId();

        if (!$odontogramId) {
            $newOdontogram = new Odontogram();
            $newOdontogram->setVisit($appointment);
            $newOdontogram->setStatus('Actiu');

            $entityManager->persist($newOdontogram);
            $entityManager->flush();

            $patient->setLastOdontogramId($newOdontogram->getId());
            $entityManager->flush();
            
            $odontogramId = $newOdontogram->getId();
        }

        return $this->redirectToRoute('app_odontogram_view', ['id' => $odontogramId]);
    }

    #[Route('/{id}', name: 'app_appointment_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Appointment $appointment): JsonResponse
    {
        return $this->json([
            'id' => $appointment->getId(),
            'date' => $appointment->getVisitDate() ? $appointment->getVisitDate()->format('Y-m-d') : null,
            'time' => $appointment->getVisitTime() ? $appointment->getVisitTime()->format('H:i') : null,
            'reason' => $appointment->getConsultationReason() ?? '',
            'observations' => $appointment->getObservations() ?? '',
            'status' => $appointment->getStatus() ?? 'Programada',
            'duration' => $appointment->getDurationMinutes(),
            'patient' => [
                'id' => $appointment->getPatient()?->getId(),
                'name' => $appointment->getPatient() ? ($appointment->getPatient()->getFirstName() . ' ' . $appointment->getPatient()->getLastName()) : 'Pacient desconegut',
            ],
            'doctor' => [
                'id' => $appointment->getDoctor()?->getId(),
                'name' => $appointment->getDoctor() ? ($appointment->getDoctor()->getFirstName() . ' ' . $appointment->getDoctor()->getLastNames()) : 'Sense doctor',
            ],
            'box' => $appointment->getBox()?->getBoxName() ?? 'Sense box',
            'treatmentId' => $appointment->getTreatment()?->getId(),
        ]);
    }

    #[Route('/{id}/close', name: 'app_appointment_close', methods: ['POST'])]
    public function close(Appointment $appointment, EntityManagerInterface $em): JsonResponse 
    {
        $appointment->setStatus('Finalitzada');
        $em->flush();
        return $this->json(['message' => 'Cita finalitzada']);
    }

    #[Route('/{id}/update', name: 'app_appointment_update', requirements: ['id' => '\d+'], methods: ['POST', 'PUT'])]
    public function update(
        Request $request, 
        Appointment $appointment, 
        EntityManagerInterface $entityManager,
        AppointmentRepository $repository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!$data) return $this->json(['error' => 'JSON invàlid'], 400);

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->submit($data, false);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($this->isBoxOccupied($repository, $appointment)) {
                    return $this->json(['error' => 'No es pot moure la cita: el Box ja està ocupat'], 409);
                }

                $entityManager->flush();
                
                return $this->json([
                    'status' => 'updated',
                    'id' => $appointment->getId(),
                    'message' => 'Cita actualitzada'
                ]);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Error de base de dades', 'debug' => $e->getMessage()], 500);
            }
        }
        return $this->json(['error' => 'Error de validació'], 400);
    }

    #[Route('/{id}', name: 'app_appointment_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Appointment $appointment, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($appointment);
        $entityManager->flush();

        return $this->json(['message' => 'Cita eliminada correctament']);
    }
}