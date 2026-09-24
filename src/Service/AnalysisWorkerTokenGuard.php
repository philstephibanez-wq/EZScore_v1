<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class AnalysisWorkerTokenGuard
{
    public function assertAuthorized(Request $request): void
    {
        $expected = trim((string) (
            $_SERVER['ANALYSIS_WORKER_TOKEN']
            ?? $_ENV['ANALYSIS_WORKER_TOKEN']
            ?? ''
        ));

        if ($expected === '') {
            throw new ServiceUnavailableHttpException(
                null,
                'Analysis worker API is not configured.',
            );
        }

        $provided = trim((string) $request->headers->get('X-EZScore-Analysis-Token', ''));

        if ($provided === '') {
            $authorization = trim((string) $request->headers->get('Authorization', ''));
            if (str_starts_with($authorization, 'Bearer ')) {
                $provided = trim(substr($authorization, 7));
            }
        }

        if ($provided === '' || !hash_equals($expected, $provided)) {
            throw new AccessDeniedHttpException('Invalid analysis worker token.');
        }
    }
}
