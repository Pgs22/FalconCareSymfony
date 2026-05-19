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
    name: 'app:documents:materialize-disk',
    description: 'Escribe en public/uploads/documents los documentos que solo tienen binario en BD.',
)]
final class DocumentsMaterializeDiskCommand extends Command
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
        $io->note('Directorio: ' . $this->documentBinaryStorage->getUploadDir());

        $written = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->documentRepository->getAll() as $document) {
            if ($this->documentBinaryStorage->resolveAbsolutePath($document) !== null) {
                ++$skipped;
                continue;
            }

            if ($this->documentBinaryStorage->materializeOnDisk($document)) {
                ++$written;
                continue;
            }

            ++$failed;
            $io->warning(sprintf(
                'id=%d: no se pudo crear fichero (file_path=%s)',
                $document->getId(),
                $document->getFilePath() ?? '—'
            ));
        }

        $io->success(sprintf(
            'Materializados en disco: %d, ya existían: %d, fallidos: %d',
            $written,
            $skipped,
            $failed
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
