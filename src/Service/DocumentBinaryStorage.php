<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Almacenamiento canónico: public/uploads/documents/{file_path}.
 * file_path en BD es solo el nombre de fichero (sin rutas).
 * Copia opcional en file_content (Neon) para entornos sin disco compartido.
 */
final class DocumentBinaryStorage
{
    public const PUBLIC_RELATIVE_DIR = 'uploads/documents';

    public function __construct(
        private readonly string $uploadDir,
    ) {
    }

    /**
     * Guarda el upload en public/uploads/documents y devuelve nombre + bytes para Neon.
     *
     * @return array{filename: string, content: string}
     */
    public function persistUpload(UploadedFile $file, string $extension): array
    {
        $this->ensureUploadDirectoryExists();

        $filename = $this->generateUniqueFilename($extension);
        $absolute = $this->absolutePathForFilename($filename);

        try {
            $file->move($this->uploadDir, $filename);
        } catch (FileException $e) {
            throw new \RuntimeException('Could not store file in uploads/documents: ' . $e->getMessage(), 0, $e);
        }

        if (!is_file($absolute)) {
            throw new \RuntimeException(sprintf('File was not written to %s', $absolute));
        }

        $content = @file_get_contents($absolute);
        if ($content === false || $content === '') {
            @unlink($absolute);
            throw new \RuntimeException('Uploaded file is empty or unreadable after storage.');
        }

        return ['filename' => $filename, 'content' => $content];
    }

    public function generateUniqueFilename(string $extension): string
    {
        $ext = strtolower(ltrim($extension, '.'));

        return uniqid('', true) . ($ext !== '' ? '.' . $ext : '');
    }

    public function normalizeStoredFilename(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', trim($filename)));
        if ($basename === '' || str_contains($basename, '..')) {
            throw new \InvalidArgumentException('Invalid document file path.');
        }

        return $basename;
    }

    public function resolveAbsolutePath(Document $document): ?string
    {
        $relative = $document->getFilePath();
        if ($relative === null || $relative === '') {
            return null;
        }

        try {
            $filename = $this->normalizeStoredFilename($relative);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $absolute = $this->absolutePathForFilename($filename);

        return is_file($absolute) ? $absolute : null;
    }

    public function resolveBytes(Document $document): ?string
    {
        $fromDb = self::normalizeBlob($document->getFileContentRaw());
        if ($fromDb !== null && $fromDb !== '') {
            return $fromDb;
        }

        $absolute = $this->resolveAbsolutePath($document);
        if ($absolute === null) {
            return null;
        }

        $fromDisk = @file_get_contents($absolute);

        return ($fromDisk !== false && $fromDisk !== '') ? $fromDisk : null;
    }

    public function materializeOnDisk(Document $document): bool
    {
        if ($this->resolveAbsolutePath($document) !== null) {
            return true;
        }

        $bytes = self::normalizeBlob($document->getFileContentRaw());
        if ($bytes === null || $bytes === '') {
            return false;
        }

        $relative = $document->getFilePath();
        if ($relative === null || $relative === '') {
            return false;
        }

        try {
            $filename = $this->normalizeStoredFilename($relative);
        } catch (\InvalidArgumentException) {
            return false;
        }

        $this->ensureUploadDirectoryExists();
        $written = @file_put_contents($this->absolutePathForFilename($filename), $bytes);

        return $written !== false && $written > 0;
    }

    public function deleteLocalFile(Document $document): void
    {
        $absolute = $this->resolveAbsolutePath($document);
        if ($absolute !== null) {
            @unlink($absolute);
        }
    }

    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }

    private function absolutePathForFilename(string $filename): string
    {
        return rtrim($this->uploadDir, '/\\') . DIRECTORY_SEPARATOR . $this->normalizeStoredFilename($filename);
    }

    private function ensureUploadDirectoryExists(): void
    {
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0775, true) && !is_dir($this->uploadDir)) {
                throw new \RuntimeException(sprintf('Cannot create upload directory: %s', $this->uploadDir));
            }
        }
    }

    public static function normalizeBlob(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (\is_resource($raw)) {
            $data = stream_get_contents($raw);

            return $data === false ? null : $data;
        }

        if (\is_string($raw)) {
            return $raw;
        }

        return null;
    }
}
