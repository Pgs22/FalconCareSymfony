<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\DocumentRepository;
use App\Service\DocumentBinaryStorage;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:documents:verify-team-access',
    description: 'Comprueba que cada documento tenga binario en Neon (file_content) para visualización entre equipos.',
)]
final class DocumentsVerifyTeamAccessCommand extends Command
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentBinaryStorage $documentBinaryStorage,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_NONE, 'Salida JSON (para scripts CI)')
            ->addOption('fail-on-missing', null, InputOption::VALUE_NONE, 'Exit code 1 si falta binario en Neon');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->columnExists()) {
            $msg = 'Falta columna document.file_content. Ejecuta: php bin/console doctrine:migrations:migrate';
            if ($input->getOption('json')) {
                $output->writeln(json_encode(['ok' => false, 'error' => $msg], \JSON_THROW_ON_ERROR));

                return Command::FAILURE;
            }
            $io->error($msg);

            return Command::FAILURE;
        }

        $ok = 0;
        $missingNeon = [];
        $backfillable = [];

        foreach ($this->documentRepository->getAll() as $document) {
            $id = (int) $document->getId();
            $bytes = $this->documentRepository->loadFileContentBinary($id);
            if ($bytes !== null && $bytes !== '') {
                ++$ok;
                continue;
            }

            $canBackfill = $this->documentBinaryStorage->resolveAbsolutePath($document) !== null;
            $row = [
                'id' => $id,
                'file_path' => $document->getFilePath(),
                'local_disk' => $canBackfill,
            ];
            $missingNeon[] = $row;
            if ($canBackfill) {
                $backfillable[] = $id;
            }
        }

        $total = $ok + \count($missingNeon);
        $payload = [
            'ok' => $missingNeon === [],
            'total' => $total,
            'with_neon_binary' => $ok,
            'missing_neon_binary' => $missingNeon,
            'backfillable_on_this_machine' => $backfillable,
        ];

        if ($input->getOption('json')) {
            $output->writeln(json_encode($payload, \JSON_THROW_ON_ERROR));

            return ($input->getOption('fail-on-missing') && $missingNeon !== []) ? Command::FAILURE : Command::SUCCESS;
        }

        $io->title('Documentos — acceso entre equipo (Neon file_content)');
        $io->writeln(sprintf('Total: %d | Con binario en Neon: %d | Sin binario: %d', $total, $ok, \count($missingNeon)));

        if ($missingNeon !== []) {
            $io->warning('Estos documentos NO se pueden previsualizar en otro portátil hasta re-subirlos o hacer backfill:');
            foreach ($missingNeon as $row) {
                $disk = $row['local_disk'] ? 'sí (backfill posible aquí)' : 'no';
                $io->writeln(sprintf('  id=%d  file_path=%s  disco local: %s', $row['id'], $row['file_path'] ?? '—', $disk));
            }
            if ($backfillable !== []) {
                $io->note('En el PC que tiene el PDF en disco: php bin/console app:documents:backfill-binary');
            }
        } else {
            $io->success('Todos los documentos tienen binario en Neon. Cualquier compañero puede visualizarlos.');
        }

        if ($input->getOption('fail-on-missing') && $missingNeon !== []) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function columnExists(): bool
    {
        try {
            $this->connection->executeQuery('SELECT file_content FROM document LIMIT 0');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
