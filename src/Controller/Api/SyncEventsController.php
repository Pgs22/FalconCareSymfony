<?php

namespace App\Controller\Api;

use App\Repository\PatientRepository;
use App\Service\RealtimeSyncPublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/events')]
final class SyncEventsController extends AbstractController
{
    #[Route('/sync', name: 'api_events_sync', methods: ['GET'])]
    public function __invoke(RealtimeSyncPublisher $syncPublisher, PatientRepository $patientRepository): Response
    {
        $response = new StreamedResponse(function () use ($syncPublisher, $patientRepository): void {
            @set_time_limit(0);
            echo ": connected\n\n";
            @ob_flush();
            @flush();

            $filePath = $syncPublisher->getStoragePath();
            $lastHeartbeatAt = time();
            $lastOffset = 0;
            $lastAllergiesChecksum = null;
            $lastAllergiesProbeAt = 0;

            while (!connection_aborted()) {
                if (is_file($filePath)) {
                    clearstatcache(true, $filePath);
                    $size = filesize($filePath);
                    if (is_int($size) && $size > $lastOffset) {
                        $handle = @fopen($filePath, 'rb');
                        if ($handle !== false) {
                            if ($lastOffset > 0) {
                                fseek($handle, $lastOffset);
                            }

                            while (($line = fgets($handle)) !== false) {
                                $lastOffset = ftell($handle) ?: $lastOffset;
                                $decoded = json_decode(trim($line), true);
                                if (!is_array($decoded)) {
                                    continue;
                                }

                                if (isset($decoded['topics']) && is_array($decoded['topics'])) {
                                    $payload = ['topics' => array_values($decoded['topics'])];
                                } elseif (isset($decoded['topic']) && is_string($decoded['topic'])) {
                                    $payload = ['topic' => $decoded['topic']];
                                } else {
                                    continue;
                                }

                                echo 'data: ' . json_encode($payload) . "\n\n";
                                @ob_flush();
                                @flush();
                            }

                            fclose($handle);
                        }
                    }
                }

                if ((time() - $lastHeartbeatAt) >= 20) {
                    echo ": heartbeat\n\n";
                    @ob_flush();
                    @flush();
                    $lastHeartbeatAt = time();
                }

                if ((time() - $lastAllergiesProbeAt) >= 5) {
                    $checksum = $patientRepository->getAllergiesStateChecksum();
                    if ($lastAllergiesChecksum !== null && $checksum !== $lastAllergiesChecksum) {
                        echo 'data: {"topic":"allergies.changed"}' . "\n\n";
                        @ob_flush();
                        @flush();
                    }
                    $lastAllergiesChecksum = $checksum;
                    $lastAllergiesProbeAt = time();
                }

                usleep(500000);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
