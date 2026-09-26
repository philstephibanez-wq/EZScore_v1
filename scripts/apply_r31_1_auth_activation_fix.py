#!/usr/bin/env python3
from pathlib import Path
from datetime import datetime
import re, shutil

ROOT = Path(__file__).resolve().parents[1]
STAMP = datetime.now().strftime("%Y%m%d-%H%M%S")
BACKUP = ROOT / "var" / "backup" / f"r31-1-auth-activation-{STAMP}"

def backup(path):
    path = Path(path)
    if not path.exists():
        return
    dst = BACKUP / path.relative_to(ROOT)
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(path, dst)

def save(path, text, label):
    path = Path(path)
    old = path.read_text(encoding="utf-8")
    if old == text:
        print(f"[OK] {label}: already applied")
        return
    backup(path)
    path.write_text(text, encoding="utf-8", newline="\n")
    print(f"[OK] {label}")

def install_translations():
    for name in ("admin_users.fr.yaml", "admin_users.en.yaml"):
        src = ROOT / "scripts/_payload" / name
        dst = ROOT / "translations" / name
        content = src.read_text(encoding="utf-8")
        if dst.exists() and dst.read_text(encoding="utf-8") == content:
            print(f"[OK] {name}: already installed")
            continue
        backup(dst)
        dst.write_text(content, encoding="utf-8", newline="\n")
        print(f"[OK] {name}")

def patch_google():
    path = ROOT / "src/Controller/GoogleOAuthController.php"
    text = path.read_text(encoding="utf-8")
    old = "return $clients->getClient('google_main')->redirect(['email', 'profile']);"
    new = """return $clients->getClient('google_main')->redirect(
            ['email', 'profile'],
            ['prompt' => 'select_account'],
        );"""
    if new in text:
        print("[OK] Google account chooser: already applied")
    elif old in text:
        save(path, text.replace(old, new, 1), "Google account chooser")
    else:
        raise RuntimeError("Google redirect anchor not found")

def patch_registration_service():
    path = ROOT / "src/Service/RegistrationService.php"
    text = path.read_text(encoding="utf-8")
    if "forceRenewActivationForUser" in text:
        print("[OK] Admin activation token service: already applied")
        return
    marker = "    private function issueActivationToken(User $user): string\n"
    method = """    /**
     * Administrative resend: bypasses the public resend cooldown.
     *
     * @return array{user: User, token: string}|null
     */
    public function forceRenewActivationForUser(User $user): ?array
    {
        if ($user->isEmailVerified()) {
            return null;
        }

        $token = $this->issueActivationToken($user);
        $this->em->flush();

        return ['user' => $user, 'token' => $token];
    }

"""
    if marker not in text:
        raise RuntimeError("RegistrationService anchor not found")
    save(path, text.replace(marker, method + marker, 1), "Admin activation token service")

def patch_admin_controller():
    path = ROOT / "src/Controller/AdminUserController.php"
    text = path.read_text(encoding="utf-8")

    import_anchor = "use App\\Service\\UserManager;\n"
    imports = (
        "use App\\Service\\Exception\\ActivationMailException;\n"
        "use App\\Service\\RegistrationMailer;\n"
        "use App\\Service\\RegistrationService;\n"
        "use Doctrine\\ORM\\EntityManagerInterface;\n"
        "use Symfony\\Contracts\\Translation\\TranslatorInterface;\n"
    )
    if "use App\\Service\\RegistrationMailer;" not in text:
        if import_anchor not in text:
            raise RuntimeError("AdminUserController import anchor not found")
        text = text.replace(import_anchor, import_anchor + imports, 1)

    if "admin_user_activation_resend" not in text:
        marker = "    #[Route('/{id}/delete', name: 'admin_user_delete', requirements: ['id' => '\\\\d+'], methods: ['POST'])]\n"
        methods = """    #[Route('/{id}/activation/resend', name: 'admin_user_activation_resend', requirements: ['id' => '\\\\d+'], methods: ['POST'])]
    public function resendActivation(
        User $user,
        Request $request,
        RegistrationService $registration,
        RegistrationMailer $mailer,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'resend_activation_user_'.$user->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException();
        }

        if ($user->isEmailVerified()) {
            $this->addFlash('success', $translator->trans('admin_users.activation.already_verified', [], 'admin_users'));
            return $this->redirectToRoute('admin_users', $this->returnFilters($request));
        }

        $result = $registration->forceRenewActivationForUser($user);

        try {
            if ($result !== null) {
                $mailer->sendActivation($result['user'], $result['token']);
            }
            $this->addFlash('success', $translator->trans('admin_users.activation.resend_sent', [], 'admin_users'));
        } catch (ActivationMailException) {
            $this->addFlash('error', $translator->trans('admin_users.activation.resend_failed', [], 'admin_users'));
        }

        return $this->redirectToRoute('admin_users', $this->returnFilters($request));
    }

    #[Route('/{id}/activation/verify', name: 'admin_user_activation_verify', requirements: ['id' => '\\\\d+'], methods: ['POST'])]
    public function verifyEmailManually(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'verify_email_user_'.$user->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException();
        }

        if (!$user->isEmailVerified()) {
            $user->markEmailVerifiedForManagedAccount();
            $em->flush();
        }

        $this->addFlash('success', $translator->trans('admin_users.activation.manual_verified', [], 'admin_users'));
        return $this->redirectToRoute('admin_users', $this->returnFilters($request));
    }

"""
        if marker not in text:
            raise RuntimeError("AdminUserController delete anchor not found")
        text = text.replace(marker, methods + marker, 1)

    save(path, text, "Admin activation actions")

def patch_admin_template():
    path = ROOT / "templates/admin/users.html.twig"
    text = path.read_text(encoding="utf-8")
    if "admin_user_activation_resend" in text:
        print("[OK] Admin activation UI: already applied")
        return

    text = text.replace(
        '/assets/css/admin-users.css?v=20260925r20',
        '/assets/css/admin-users.css?v=20260926r31_1',
        1
    )

    old = """<label class="check active-check"><input type="checkbox" name="active" value="1" {{ user.active ? 'checked' : '' }}>{{ 'users.active'|trans }}</label>
<span class="provider-state">{{ user.googleSub ? ('users.google_linked'|trans) : '—' }}</span>
<input type="password" name="password" minlength="10" placeholder="{{ 'users.password_unchanged'|trans }}">
</form>
<div class="user-actions">"""

    new = """<label class="check active-check"><input type="checkbox" name="active" value="1" {{ user.active ? 'checked' : '' }}>{{ user.active ? ('admin_users.account.active'|trans({}, 'admin_users')) : ('admin_users.account.inactive'|trans({}, 'admin_users')) }}</label>
<span class="provider-state">{{ user.googleSub ? ('users.google_linked'|trans) : '—' }}</span>
<input type="password" name="password" minlength="10" placeholder="{{ 'users.password_unchanged'|trans }}">
</form>

<div class="user-verification">
    <span class="verification-badge {{ user.emailVerified ? 'verified' : 'unverified' }}">
        {{ user.emailVerified ? ('admin_users.activation.verified'|trans({}, 'admin_users')) : ('admin_users.activation.unverified'|trans({}, 'admin_users')) }}
    </span>
    {% if user.emailVerifiedAt %}
        <small>{{ 'admin_users.activation.verified_on'|trans({'%date%': user.emailVerifiedAt|date('d/m/Y H:i')}, 'admin_users') }}</small>
    {% else %}
        <div class="verification-actions">
            <form method="post" action="{{ path('admin_user_activation_resend', {'_locale': app.request.locale, id: user.id}) }}">
                <input type="hidden" name="_token" value="{{ csrf_token('resend_activation_user_' ~ user.id) }}">
                {% for key,value in filters %}{% if value is not null and value != '' %}<input type="hidden" name="filter_{{ key }}" value="{{ value }}">{% endif %}{% endfor %}
                <input type="hidden" name="filter_page" value="{{ pager.page }}">
                <button type="submit" class="secondary-button">{{ 'admin_users.activation.resend'|trans({}, 'admin_users') }}</button>
            </form>

            <form method="post" action="{{ path('admin_user_activation_verify', {'_locale': app.request.locale, id: user.id}) }}"
                  onsubmit="return confirm('{{ 'admin_users.activation.confirm_manual'|trans({}, 'admin_users')|e('js') }}');">
                <input type="hidden" name="_token" value="{{ csrf_token('verify_email_user_' ~ user.id) }}">
                {% for key,value in filters %}{% if value is not null and value != '' %}<input type="hidden" name="filter_{{ key }}" value="{{ value }}">{% endif %}{% endfor %}
                <input type="hidden" name="filter_page" value="{{ pager.page }}">
                <button type="submit" class="secondary-button">{{ 'admin_users.activation.verify_manual'|trans({}, 'admin_users') }}</button>
            </form>
        </div>
    {% endif %}
</div>

<div class="user-actions">"""
    if old not in text:
        raise RuntimeError("Admin user row anchor not found")
    save(path, text.replace(old, new, 1), "Admin activation UI")

def patch_login_template():
    path = ROOT / "templates/auth/login.html.twig"
    text = path.read_text(encoding="utf-8")
    text = text.replace(
        "{{ 'auth.login.remember_me'|trans }}",
        "{{ 'login.remember_me'|trans({}, 'admin_users') }}",
        1
    )

    if "login.resend_activation" not in text:
        anchor = "    {% if error %}<div class=\"flash flash-error\">{{ error.messageKey|trans(error.messageData, 'security') }}</div>{% endif %}\n"
        addition = anchor + """    {% if error and error.messageKey == 'auth.account.email_not_verified' and last_username %}
    <form method="post" action="{{ path('app_registration_resend', {'_locale': app.request.locale}) }}" class="auth-inline-action">
        <input type="hidden" name="_token" value="{{ csrf_token('resend_activation') }}">
        <input type="hidden" name="email" value="{{ last_username }}">
        <button type="submit" class="secondary-button">{{ 'login.resend_activation'|trans({}, 'admin_users') }}</button>
    </form>
    {% endif %}
"""
        if anchor not in text:
            raise RuntimeError("Login error anchor not found")
        text = text.replace(anchor, addition, 1)

    save(path, text, "Login resend activation + remember-me translation")

def patch_css():
    path = ROOT / "public/assets/css/admin-users.css"
    text = path.read_text(encoding="utf-8")
    if "R31.1 activation controls" in text:
        print("[OK] Admin activation CSS: already applied")
        return
    css = """
/* R31.1 activation controls */
.user-admin-row{grid-template-columns:minmax(0,1fr) minmax(250px,auto) auto;align-items:center}
.user-verification{display:grid;gap:5px;min-width:230px}
.verification-badge{display:inline-flex;width:max-content;padding:4px 8px;border-radius:999px;font-size:10px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
.verification-badge.verified{border:1px solid #357b55;color:#8de7ae;background:rgba(35,110,69,.16)}
.verification-badge.unverified{border:1px solid #9a692b;color:#ffd18a;background:rgba(132,83,23,.18)}
.user-verification small{color:#91a3ad}
.verification-actions{display:flex;flex-wrap:wrap;gap:6px}
.verification-actions form{margin:0}
.secondary-button{min-height:32px;padding:5px 9px;white-space:nowrap}
@media(max-width:1180px){.user-admin-row{grid-template-columns:1fr}.user-verification{min-width:0}}
@media(max-width:680px){.verification-actions{display:grid;grid-template-columns:1fr}.verification-actions .secondary-button{width:100%;min-height:40px}}
"""
    save(path, text.rstrip() + "\n" + css, "Admin activation CSS")

def main():
    install_translations()
    patch_google()
    patch_registration_service()
    patch_admin_controller()
    patch_admin_template()
    patch_login_template()
    patch_css()
    print(f"[OK] Backup: {BACKUP}")
    print("[OK] R31.1 authentication / activation fix applied.")

if __name__ == "__main__":
    main()
