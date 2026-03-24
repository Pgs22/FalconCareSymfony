<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Repository\OdontogramRepository;
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
            $result[] = [
                'id' => $appointment->getId(),
                'time' => $appointment->getVisitTime()->format('H:i'),
                'duration' => $appointment->getDurationMinutes(),
                'status' => $appointment->getStatus(),
                'patientName' => $appointment->getPatient()->getFirstName() . ' ' . $appointment->getPatient()->getLastName(),
                'doctorName' => $appointment->getDoctor()->getFirstName() . ' ' . $appointment->getDoctor()->getLastNames(),
                'box' => $appointment->getBox()->getBoxName(),
                'reason' => $appointment->getConsultationReason()
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
            return $this->json(['error' => 'Fecha no válida'], 400);
        }

        return $this->json($this->serializeAppointments($appointments));
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

            $busy = $repository->findOneBy([ 
                'visitDate' => $appointment->getVisitDate(),
                'visitTime' => $appointment->getVisitTime(),
                'box' => $appointment->getBox()
            ]);

            if ($busy) {
                return $this->json(['error' => 'El Box ya está ocupado'], Response::HTTP_CONFLICT);
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
    public function open(
        Appointment $appointment, 
        OdontogramRepository $odontogramRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $patient = $appointment->getPatient();
        $lastId = $patient->getLastOdontogramId();

        // 1. Buscamos el último registro físico
        $lastOdontogram = $lastId ? $odontogramRepository->find($lastId) : null;

        // 2. ¿Necesitamos crear uno nuevo? 
        // Solo si no hay ninguno previo O si el último ya se cerró manualmente.
        if (!$lastOdontogram || $lastOdontogram->getStatus() === 'Cerrado') {
            $newOdontogram = new Odontogram();
            $newOdontogram->setVisit($appointment);
            $newOdontogram->setStatus('En Proceso'); // Empieza abierto para trabajar

            $entityManager->persist($newOdontogram);
            $entityManager->flush();

            // Actualizamos al paciente para que sepa cuál es su "foto" actual
            $patient->setLastIdOdontogram($newOdontogram->getId());
            $entityManager->flush();
            
            $odontogramId = $newOdontogram->getId();
        } else {
            // Si el último sigue "En Proceso", seguimos usando el mismo
            $odontogramId = $lastOdontogram->getId();
        }

        // 3. Redirigimos a Angular pasándole el ID
        return $this->redirectToRoute('app_odontogram_view', ['id' => $odontogramId]);
    }


    #[Route('/{id}', name: 'app_appointment_show', methods: ['GET'])]
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

    #[Route('/{id}/close', name: 'app_appointment_close', methods: ['POST'])]
    public function close(
        Appointment $appointment, 
        Request $request, 
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $shouldCloseOdontogram = $data['closeOdontogram'] ?? false;

        // 1. Cerramos la cita siempre
        $appointment->setStatus('Finalizada');

        // 2. Cerramos el odontograma solo si Angular nos lo pide
        $odontogram = $appointment->getOdontogram();
        if ($odontogram && $shouldCloseOdontogram) {
            $odontogram->setStatus('Cerrado');
        }

        $entityManager->flush();

        return $this->json(['message' => 'Cita procesada correctamente']);
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
                ->andWhere('a.id != :currentId')
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

    #[Route('/{id}', name: 'app_appointment_delete', methods: ['DELETE'])]
    public function delete(Appointment $appointment, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($appointment);
        $entityManager->flush();

        return $this->json(['message' => 'Cita eliminada correctamente']);
    }
}
