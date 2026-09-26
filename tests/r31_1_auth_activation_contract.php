<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$p = file_get_contents($root.'/scripts/apply_r31_1_auth_activation_fix.py');
$fr = file_get_contents($root.'/translations/admin_users.fr.yaml');
$checks = [
 'Google chooser'=>str_contains($p, "['prompt' => 'select_account']"),
 'Admin force token'=>str_contains($p, 'forceRenewActivationForUser'),
 'Resend route'=>str_contains($p, 'admin_user_activation_resend'),
 'Manual verify route'=>str_contains($p, 'admin_user_activation_verify'),
 'Manual verify user method'=>str_contains($p, 'markEmailVerifiedForManagedAccount'),
 'Mailer used'=>str_contains($p, 'RegistrationMailer'),
 'Mail exception handled'=>str_contains($p, 'ActivationMailException'),
 'Email verification UI'=>str_contains($p, 'verification-badge'),
 'Active account separate'=>str_contains($p, 'admin_users.account.active'),
 'Login resend button'=>str_contains($p, 'login.resend_activation'),
 'Remember translation'=>str_contains($fr, 'remember_me:'),
 'Mobile controls'=>str_contains($p, '@media(max-width:680px)'),
];
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "OK: $label\n";}
echo "\n".count($checks)." R31.1 auth/activation checks passed.\n";
