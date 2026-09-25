<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Analysis\AnalysisJob;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SongStemWorker
{
    public function __construct(
        private readonly SongStemJobService $jobs,
        private readonly SongStemStorage $storage,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function processOne(): bool
    {
        $job = $this->jobs->claimNext();
        if (!$job instanceof AnalysisJob) {
            return false;
        }

        $song = $job->getSong();

        try {
            $source = $this->storage->sourcePath($song);
            if (!is_file($source)) {
                throw new \RuntimeException('Source audio file is missing.');
            }

            $audioHash = (string) $song->getAudioSha256();
            if (!preg_match('/^[a-f0-9]{64}$/', $audioHash)) {
                throw new \RuntimeException('Source audio SHA-256 is missing or invalid.');
            }

            $storageRoot = $this->storage->storageRoot($song);
            if (!is_dir($storageRoot) && !mkdir($storageRoot, 0775, true) && !is_dir($storageRoot)) {
                throw new \RuntimeException('Unable to create persistent stem storage.');
            }

            $python = $this->pythonExecutable();
            $script = $this->projectDir . DIRECTORY_SEPARATOR . 'analysis' . DIRECTORY_SEPARATOR . 'stems_only.py';
            if (!is_file($script)) {
                throw new \RuntimeException('STEM worker script is missing.');
            }

            $command = [
                $python,
                $script,
                '--source', $source,
                '--audio-hash', $audioHash,
                '--storage-root', $storageRoot,
                '--progress-file', $this->storage->progressPath($song),
            ];

            if (($job->getRequestData()['force'] ?? false) === true) {
                $command[] = '--force';
            }

            $exitCode = $this->runProcess($command, $this->storage->logPath($song));
            if ($exitCode !== 0) {
                throw new \RuntimeException(sprintf('stems_worker_exit_%d', $exitCode));
            }

            $manifest = $this->storage->manifest($song);
            if (!is_array($manifest) || !$this->storage->hasCompleteStems($song)) {
                throw new \RuntimeException('stems_manifest_incomplete');
            }

            $this->storage->pruneOtherAudioHashes($song);

            $this->jobs->complete($job, [
                'schema_version' => 'ezscore.stems.v1',
                'scope' => 'stems_only',
                'audio_sha256' => $audioHash,
                'manifest' => $manifest,
                'artifacts' => array_values(SongStemStorage::STEMS),
            ]);
        } catch (\Throwable $exception) {
            $this->jobs->fail($job, $exception->getMessage());
        }

        return true;
    }

    /**
     * @param list<string> $command
     */
    private function runProcess(array $command, string $logPath): int
    {
        set_time_limit(0);

        $logDir = dirname($logPath);
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            throw new \RuntimeException('Unable to create STEM worker log directory.');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logPath, 'a'],
            2 => ['file', $logPath, 'a'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->projectDir);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start STEM Python process.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        return proc_close($process);
    }

    private function pythonExecutable(): string
    {
        $configured = trim((string) (
            $_SERVER['EZSCORE_STEM_PYTHON']
            ?? $_ENV['EZSCORE_STEM_PYTHON']
            ?? getenv('EZSCORE_STEM_PYTHON')
            ?: ''
        ));

        return $configured !== '' ? $configured : 'python';
    }
}
