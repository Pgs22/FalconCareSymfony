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
            $visitTime = $appointment->getVisitTime();
            
            $duration = $appointment->getDurationMinutes() ?? 15;
            $cleaning = Appointment::CLEANING_TIME; 
            
            $isUrgency = $appointment->isUrgency() ?? false;
            $status = $appointment->getStatus() ?? 'Pendent';
            
            if ($status === 'Finalitzada') {
                $color = '#9e9e9e';
            } elseif ($isUrgency) {
                $color = '#e74c3c';
            } else {
                $color = '#00bcd4';
            }

            $result[] = [
                'id' => $appointment->getId(),
                'time' => $visitTime ? $visitTime->format('H:i') : '--:--',
                'duration' => $duration,
                'cleaningTime' => $cleaning,
                'totalBlockTime' => $duration + $cleaning,
                
                'patientName' => $appointment->getPatient() 
                    ? $appointment->getPatient()->getFirstName() . ' ' . $appointment->getPatient()->getLastName() 
                    : 'Sense Pacient',
                
                'doctorName' => $appointment->getDoctor() 
                    ? $appointment->getDoctor()->getFirstName() . ' ' . $appointment->getDoctor()->getLastNames() 
                    : 'Sense Doctor assignat',
                
                'boxName' => $appointment->getBox() ? $appointment->getBox()->getBoxName() : 'Sense Box',
                
                'color' => $color,
                
                'isUrgency' => $isUrgency,
                'isFirstVisit' => $appointment->isFirstVisit() ?? false,
                'reason' => $appointment->getConsultationReason() ?? '',
                'status' => $status
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

    #[Route('/daily', name: 'app_appointment_daily', methods: ['GET'])]
    public function daily(Request $request, AppointmentRepository $repo): JsonResponse 
    {
        $fechaStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        
        try {
            $fecha = new \DateTime($fechaStr);
            
            $appointments = $repo->findByDate($fecha);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Format de data no vàlid. Utilitza YYYY-MM-DD'], 400);
        }

        return $this->json($this->serializeAppointments($appointments));
    }

    private function isBoxOccupied(AppointmentRepository $repository, Appointment $newApp): bool 
    {
        // Solo le preguntamos al repo si hay citas que solapen
        $overlaps = $repository->findOverlappingAppointments(
            $newApp->getVisitDate(),
            $newApp->getVisitTime(),
            (int)($newApp->getDurationMinutes() ?? 15),
            $newApp->getBox()->getId(),
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

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->submit($data); 

        if ($form->isSubmitted() && $form->isValid()) {

            if (!empty($data['isFirstVisit'])) {
                $appointment->setFirstVisit(true);
            }
            if (!empty($data['isUrgency'])) {
                $appointment->setUrgency(true);
            }

            if (isset($data['durationMinutes'])) {
                $appointment->setDurationMinutes((int)$data['durationMinutes']);
            }

            if ($appointment->getDurationMinutes() === null) {
                $appointment->setDurationMinutes(15);
            }

            if ($this->isBoxOccupied($repository, $appointment)) {
                return $this->json(['error' => 'El Box està ocupat en aquesta franja horària'], Response::HTTP_CONFLICT);
            }

            $entityManager->persist($appointment);
            $entityManager->flush();

            return $this->json([
                'id' => $appointment->getId(),
                'duration' => $appointment->getDurationMinutes(),
                'message' => 'Cita creada amb èxit'
            ], Response::HTTP_CREATED);
        }

        return $this->json(['errors' => 'Dades invàlides'], Response::HTTP_BAD_REQUEST);
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
            $newOdontogram->setStatus('Abierto');

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
            'date' => $appointment->getVisitDate()->format('Y-m-d'),
            'time' => $appointment->getVisitTime()->format('H:i'),
            'reason' => $appointment->getConsultationReason(),
            'observations' => $appointment->getObservations(),
            'status' => $appointment->getStatus(),
            'duration' => $appointment->getDurationMinutes(),
            'patient' => [
                'id' => $appointment->getPatient()->getId(),
                'name' => $appointment->getPatient()->getFirstName() . ' ' . $appointment->getPatient()->getLastName(),
            ],
            'doctor' => [
                'id' => $appointment->getDoctor()->getId(),
                'name' => $appointment->getDoctor()->getFirstName() . ' ' . $appointment->getDoctor()->getLastNames(),
            ],
            'box' => $appointment->getBox()->getBoxName(),
            'treatmentId' => $appointment->getTreatment() ? $appointment->getTreatment()->getId() : null,
        ]);
    }

    #[Route('/{id}/close', name: 'app_appointment_close', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function close(Appointment $appointment, EntityManagerInterface $em): JsonResponse 
    {
        $appointment->setStatus('Finalitzada');
        $em->flush();

        return $this->json(['message' => 'Cita tancada']);
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

        $form = $this->createForm(AppointmentType::class, $appointment, ['csrf_protection' => false]);
        $form->submit($data, false);

        if ($form->isSubmitted() && $form->isValid()) {
            
            if ($this->isBoxOccupied($repository, $appointment)) {
                return $this->json(['error' => 'No es pot moure la cita: el Box ja està ocupat'], 409);
            }

            $entityManager->flush();
            return $this->json([
                'status' => 'updated',
                'id' => $appointment->getId(),
                'duration' => $appointment->getDurationMinutes(),
                'message' => 'Cita actualitzada'
            ]);
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