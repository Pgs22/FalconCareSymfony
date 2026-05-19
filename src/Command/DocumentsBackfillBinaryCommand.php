<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\DocumentRepository;
use App\Service\DocumentBinaryStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:documents:backfill-binary',
    description: 'Sube a Neon (file_content) los PDF que solo existen en public/uploads/documents de este PC.',
)]
final class DocumentsBackfillBinaryCommand extends Command
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentBinaryStorage $documentBinaryStorage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Origen disco: ' . $this->documentBinaryStorage->getUploadDir());
        $io->note('Destino: columna document.file_content en Neon (compartida por todo el equipo).');

        $updated = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($this->documentRepository->getAll() as $document) {
            $id = (int) $document->getId();
            $existing = $this->documentRepository->loadFileContentBinary($id);
            if ($existing !== null && $existing !== '') {
                ++$skipped;
                continue;
            }

            $absolute = $this->documentBinaryStorage->resolveAbsolutePath($document);
            if ($absolute === null) {
                ++$missing;
                $io->warning(sprintf(
                    'id=%d: sin fichero local (%s)',
                    $id,
                    $document->getFilePath() ?? '—'
                ));
                continue;
            }

            $bytes = @file_get_contents($absolute);
            if ($bytes === false || $bytes === '') {
                ++$missing;
                continue;
            }

            $this->documentRepository->persistFileContentBinary($id, $bytes);
            ++$updated;
            $io->writeln(sprintf('  id=%d → %d bytes', $id, \strlen($bytes)));
        }

        $io->success(sprintf(
            'Backfill: %d guardados en Neon, %d ya tenían binario, %d sin fichero en esta máquina.',
            $updated,
            $skipped,
            $missing
        ));

        return Command::SUCCESS;
    }
}
