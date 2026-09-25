<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;

function r22_check(bool $condition, string $message): void
{
    global $checks;
    ++$checks;

    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: {$message}\n");
}

$user = file_get_contents($root.'/src/Domain/User/User.php');
r22_check(is_string($user) && str_contains($user, 'notifyNewSongs'), 'user notification preference exists');
r22_check(str_contains($user, 'wantsNewSongNotifications'), 'user preference getter exists');

$delivery = file_get_contents($root.'/src/Domain/Song/SongPublicationNotification.php');
r22_check(is_string($delivery) && str_contains($delivery, 'uniq_song_publication_notification'), 'delivery anti-duplicate unique key exists');
r22_check(str_contains($delivery, 'markFailed'), 'failed delivery is journaled');

$mailer = file_get_contents($root.'/src/Service/SongPublicationMailer.php');
r22_check(is_string($mailer) && str_contains($mailer, 'u.active = true'), 'inactive users excluded');
r22_check(str_contains($mailer, 'u.emailVerifiedAt IS NOT NULL'), 'unverified emails excluded');
r22_check(str_contains($mailer, 'u.notifyNewSongs = true'), 'opt-out respected');
r22_check(str_contains($mailer, 'wasSent()'), 'sent recipients skipped');
r22_check(str_contains($mailer, 'app_song_workspace'), 'mail links to song');
r22_check(str_contains($mailer, 'app_profile'), 'mail links to preferences');

$controller = file_get_contents($root.'/src/Controller/CatalogController.php');
r22_check(is_string($controller) && substr_count($controller, '$publicationMailer->announce($song)') >= 2, 'workspace and admin publication trigger mailing');
r22_check(str_contains($controller, '$wasPublished = $song->isPublished();'), 'mailing only follows publication transition');

$profile = file_get_contents($root.'/templates/profile/index.html.twig');
r22_check(is_string($profile) && str_contains($profile, 'notify_new_songs'), 'profile exposes mailing preference');

$migration = file_get_contents($root.'/migrations/Version20260925230000.php');
r22_check(is_string($migration) && str_contains($migration, 'song_publication_notifications'), 'delivery journal migration exists');
r22_check(str_contains($migration, 'notify_new_songs BOOLEAN NOT NULL DEFAULT 1'), 'preference defaults enabled');

$cdc = file_get_contents($root.'/docs/CAHIER_DES_CHARGES.md');
r22_check(is_string($cdc) && str_contains($cdc, '## 17. Implémentation R22'), 'CDC documents implemented behavior');

fwrite(STDOUT, "\n{$checks} R22 mailing checks passed.\n");
