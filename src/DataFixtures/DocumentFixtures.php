<?php

namespace App\DataFixtures;

use App\Entity\Document;
use App\Entity\Patient;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DocumentFixtures extends Fixture
{
    private $faker;
    private $uploadDir;

    public function __construct(string $kernelProjectDir)
    {
        $this->faker = Factory::create('es_ES');
        // Define direct folder path [cite: 12-02-2026]
        $this->uploadDir = $kernelProjectDir . '/public/uploads/documents';
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Ensure directory exists [cite: 12-02-2026]
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }

        // 2. Fetch all existing patients [cite: 12-02-2026]
        $patients = $manager->getRepository(Patient::class)->findAll();
        
        if (empty($patients)) {
            throw new \Exception('No patients found in database. Please create patients first.');
        }

        // 3. Loop through patients and add fake documents [cite: 12-02-2026]
        foreach ($patients as $patient) {
            // Add between 1 to 3 documents per patient [cite: 12-02-2026]
            for ($j = 0; $j < rand(1, 3); $j++) {
                
                // --- PHYSICAL FILE LOGIC ---
                // Simulating a real file upload [cite: 12-02-2026]
                $tmpFilePath = tempnam(sys_get_temp_dir(), 'doc');
                file_put_contents($tmpFilePath, '%PDF-1.4 Fake Document Content');

                // Naming convention: p{id}_{uniqid}.pdf [cite: 12-02-2026]
                $newFilename = 'p' . $patient->getId() . '_' . uniqid() . '.pdf';
                
                // Move file to final destination [cite: 12-02-2026]
                copy($tmpFilePath, $this->uploadDir . '/' . $newFilename);
                unlink($tmpFilePath); // Delete temporary file [cite: 12-02-2026]

                // --- CONFIGURE DOCUMENT ENTITY ---
                $document = new Document();
                $document->setFilePath($newFilename);
                $document->setType($this->faker->randomElement(['Report', 'X-ray', 'Lab Results']));
                $document->setCaptureDate(\DateTimeImmutable::createFromMutable($this->faker->dateTimeThisYear()));
                $document->setPatient($patient); // Assign to existing patient [cite: 12-02-2026]

                $manager->persist($document);
            }
        }

        $manager->flush(); // Save all documents to database [cite: 12-02-2026]
    }
}