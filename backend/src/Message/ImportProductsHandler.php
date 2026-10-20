<?php

declare(strict_types=1);

namespace App\Message;

use App\Import\InvalidRowException;
use App\Import\ProductImporter;
use App\Import\ProductRowParser;
use App\Import\XlsxReader;
use App\Repository\ImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Exception\StopWorkerException;

final class ImportProductsHandler
{
    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly XlsxReader $reader,
        private readonly ProductRowParser $parser,
        private readonly ProductImporter $importer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ImportProductsMessage $message): void
    {
        $job = $this->jobs->find($message->jobId);
        if ($job === null) {
            return;
        }

        try {
            $rows = $this->reader->read($message->filePath);
            $job->start(count($rows));
            $this->jobs->save($job);

            foreach ($rows as $rowNumber => $cells) {
                $created = null;
                try {
                    $created = $this->importer->import(
                        $this->parser->parse($cells),
                        fn (string $warning) => $job->addError($rowNumber, $warning),
                    );
                } catch (InvalidRowException $e) {
                    $job->addError($rowNumber, $e->getMessage());
                }
                $job->rowDone($created);
                $this->jobs->save($job);
            }
            $job->finish();
        } catch (\Throwable $e) {
            $job->fail('Импорт прерван: ' . $e->getMessage());
        } finally {
            $this->jobs->save($job);
            @unlink($message->filePath);
            $this->em->clear();
        }

        // A failed flush closes the EntityManager for good; let the worker restart with a fresh one
        if (!$this->em->isOpen()) {
            throw new StopWorkerException('EntityManager closed');
        }
    }
}
