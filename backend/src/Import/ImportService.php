<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportJob;
use App\Message\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportService
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    // finfo reports some valid .xlsx files as plain zip archives
    private const MIME_TYPES = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'];

    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly MessageBusInterface $bus,
        private readonly string $storageDir,
    ) {}

    /**
     * Validates the upload, stores it and queues the import.
     *
     * @throws InvalidUploadException
     */
    public function queue(?UploadedFileInterface $file): ImportJob
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            throw new InvalidUploadException('Файл не передан (поле file)');
        }
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file->getSize() > self::MAX_BYTES) {
            throw new InvalidUploadException('Файл больше 10 МБ');
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidUploadException('Файл не загрузился, попробуйте ещё раз');
        }

        $filename = (string) $file->getClientFilename();
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new InvalidUploadException('Нужен файл с расширением .xlsx');
        }
        $tmpPath = (string) $file->getStream()->getMetadata('uri');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        if (!in_array($mime, self::MIME_TYPES, true) || !(new Xlsx())->canRead($tmpPath)) {
            throw new InvalidUploadException('Файл не является таблицей Excel (.xlsx)');
        }

        $dir = $this->storageDir . '/imports';
        is_dir($dir) || mkdir($dir, 0o775, true);
        $path = $dir . '/' . bin2hex(random_bytes(16)) . '.xlsx';
        $file->moveTo($path);

        $job = new ImportJob(mb_substr(basename($filename), 0, 255));
        $this->jobs->save($job);
        $this->bus->dispatch(new ImportProductsMessage((int) $job->getId(), $path));

        return $job;
    }
}
