<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Analysis\AnalysisJob;

final class AnalysisPayloadContract
{
    public const SCHEMA_VERSION = 'ezscore.analysis.v1';

    /**
     * Stable worker input envelope.
     *
     * The request payload itself stays deliberately opaque at this stage:
     * analyzer/module ordering is not fixed by this contract.
     */
    public function workerPayload(AnalysisJob $job): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'job_id' => $job->getId(),
            'song_id' => $job->getSong()->getId(),
            'kind' => $job->getKind(),
            'request' => $job->getRequestData(),
            'created_at' => $job->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * Validate and normalize the stable result envelope.
     *
     * Module-specific data lives under "outputs" so independent analyzers can
     * be introduced without imposing a pipeline order in the transport layer.
     */
    public function validateResult(array $payload, AnalysisJob $job): array
    {
        $schemaVersion = (string) ($payload['schema_version'] ?? '');
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported analysis schema_version "%s".',
                $schemaVersion,
            ));
        }

        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($jobId !== (int) $job->getId()) {
            throw new \InvalidArgumentException('Analysis result job_id does not match the claimed job.');
        }

        $songId = (int) ($payload['song_id'] ?? 0);
        if ($songId !== (int) $job->getSong()->getId()) {
            throw new \InvalidArgumentException('Analysis result song_id does not match the claimed job.');
        }

        $kind = trim((string) ($payload['kind'] ?? ''));
        if ($kind !== $job->getKind()) {
            throw new \InvalidArgumentException('Analysis result kind does not match the claimed job.');
        }

        $outputs = $payload['outputs'] ?? null;
        if (!is_array($outputs)) {
            throw new \InvalidArgumentException('Analysis result outputs must be an object/array.');
        }

        foreach (['artifacts', 'metrics', 'warnings'] as $field) {
            if (isset($payload[$field]) && !is_array($payload[$field])) {
                throw new \InvalidArgumentException(sprintf(
                    'Analysis result %s must be an object/array.',
                    $field,
                ));
            }
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'job_id' => $jobId,
            'song_id' => $songId,
            'kind' => $kind,
            'engine' => is_array($payload['engine'] ?? null) ? $payload['engine'] : [],
            'outputs' => $outputs,
            'artifacts' => is_array($payload['artifacts'] ?? null) ? $payload['artifacts'] : [],
            'metrics' => is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [],
            'warnings' => is_array($payload['warnings'] ?? null) ? array_values($payload['warnings']) : [],
        ];
    }
}
