<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;

function r221_check(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

$controller = file_get_contents($root.'/src/Controller/CatalogController.php');
r221_check(is_string($controller) && str_contains($controller, 'SongPublicationQueue'), 'publication uses queue service');
r221_check(!str_contains($controller, '$publicationMailer->announce'), 'HTTP publication no longer sends mail');
r221_check(substr_count($controller, '$publicationQueue->enqueue($song)') >= 2, 'workspace and admin both enqueue');

$queue = file_get_contents($root.'/src/Service/SongPublicationQueue.php');
r221_check(is_string($queue) && str_contains($queue, 'new SongPublicationMailJob'), 'queue creates persistent job');

$worker = file_get_contents($root.'/src/Service/SongPublicationWorker.php');
r221_check(is_string($worker) && str_contains($worker, 'claim_token'), 'worker uses claim token');
r221_check(str_contains($worker, "-15 minutes"), 'stale claim can be recovered');
r221_check(str_contains($worker, 'next_attempt_at'), 'worker respects retry backoff');

$job = file_get_contents($root.'/src/Domain/Song/SongPublicationMailJob.php');
r221_check(is_string($job) && str_contains($job, 'nextAttemptAt'), 'job persists next retry');
r221_check(str_contains($job, 'releaseWithError'), 'job can be released after failure');

$mailer = file_get_contents($root.'/src/Service/SongPublicationMailer.php');
r221_check(is_string($mailer) && str_contains($mailer, 'public function process'), 'mailer only processes worker job');
r221_check(str_contains($mailer, 'wasSent()'), 'worker retry skips already sent recipient');

$command = file_get_contents($root.'/src/Command/SongPublicationMailWorkerCommand.php');
r221_check(is_string($command) && str_contains($command, "name: 'app:mailing:worker'"), 'worker command exists');
r221_check(str_contains($command, "addOption('once'"), 'worker supports one-shot test');

$migration = file_get_contents($root.'/migrations/Version20260925231000.php');
r221_check(is_string($migration) && str_contains($migration, 'song_publication_mail_jobs'), 'async queue migration exists');
r221_check(str_contains($migration, 'attempts INTEGER DEFAULT 0 NOT NULL'), 'queue persists attempts');

$readme = file_get_contents($root.'/readme.md');
r221_check(is_string($readme) && str_contains($readme, 'ASYNCHRONE'), 'README documents asynchronous delivery');

fwrite(STDOUT, "\n{$checks} R22.1 async mailing checks passed.\n");
