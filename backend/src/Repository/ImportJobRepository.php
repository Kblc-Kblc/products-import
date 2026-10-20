<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;
use Doctrine\ORM\EntityManagerInterface;

final class ImportJobRepository
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function find(int $id): ?ImportJob
    {
        return $this->em->find(ImportJob::class, $id);
    }

    public function save(ImportJob $job): void
    {
        if (!$this->em->isOpen()) {
            // The EM closes after a failed flush; still record the outcome so clients stop polling
            $data = $job->toArray();
            $this->em->getConnection()->update('import_jobs', [
                'status' => $data['status'],
                'errors' => json_encode($data['errors'], JSON_UNESCAPED_UNICODE),
                'finished_at' => $data['finishedAt'],
            ], ['id' => $data['id']]);

            return;
        }
        $this->em->persist($job);
        $this->em->flush();
    }
}
