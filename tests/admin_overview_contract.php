<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;

function adminCheck(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

$controller = file_get_contents($root.'/src/Controller/AdminOverviewController.php');
adminCheck(is_string($controller), 'AdminOverviewController exists');
adminCheck(str_contains($controller, "'owners' => \$owners"), 'group owners are exposed');
adminCheck(str_contains($controller, "'owner_label' => \$ownerLabel"), 'playlist owner is resolved');
adminCheck(str_contains($controller, "'invitation_stats' => \$invitationStats"), 'playlist share state is exposed');

$template = file_get_contents($root.'/templates/admin/overview.html.twig');
adminCheck(is_string($template), 'admin overview template exists');
adminCheck(str_contains($template, 'group_rows'), 'group table is rendered');
adminCheck(str_contains($template, 'playlist_rows'), 'playlist table is rendered');

fwrite(STDOUT, "\n{$checks} admin back-office checks passed.\n");
