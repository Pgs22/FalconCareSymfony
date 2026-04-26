<?php

declare(strict_types=1);

namespace App\Util;

use App\Entity\RadiographAnnotation;

final class RadiographAnnotationSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(RadiographAnnotation $annotation): array
    {
        return [
            'id' => $annotation->getId(),
            'documentId' => $annotation->getDocument()?->getId(),
            'patientId' => $annotation->getPatient()?->getId(),
            'appointmentId' => $annotation->getAppointment()?->getId(),
            'visitId' => $annotation->getAppointment()?->getId(),
            'tool' => $annotation->getTool(),
            'label' => $annotation->getLabel(),
            'color' => $annotation->getColor(),
            'payload' => $annotation->getPayload(),
            'data' => $annotation->getPayload(),
            'createdAt' => $annotation->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $annotation->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @param iterable<RadiographAnnotation> $annotations
     *
     * @return list<array<string, mixed>>
     */
    public static function collection(iterable $annotations): array
    {
        $result = [];
        foreach ($annotations as $annotation) {
            $result[] = self::toArray($annotation);
        }

        return $result;
    }
}
