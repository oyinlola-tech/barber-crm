# Authentication and account security

This document describes authentication behavior implemented in this repository
and the operator steps needed before deployment. It is not a production
security certification.

## Authentication model

- The application uses Laravel Fortify with the `web` guard. Public account
  registration is not enabled. The initial account is provisioned with
  `php artisan app:provision-owner`; it prompts for the password without
  accepting it as a command-line option. Provisioning refuses to run if a user
  or business already exists.
- Staff accounts are created through invitations. The invitation stores a
  SHA-256 token hash, expires after 72 hours, and is single-use/revocable. The
  invitee chooses a password when accepting. Email sending must be configured;
  failed delivery revokes the invitation. Invitations are sent as email, so
  configure a private, trusted mail transport before enabling staff onboarding.
- Fortify provides login, password reset, email verification, and confirmed
  two-factor authentication with recovery codes. New staff invitations are
  marked verified on acceptance because the invitation link is delivered to
  the invited email address. Other accounts must complete signed email
  verification before protected application access.
- Login throttling uses Fortify's `login` limiter. Password hashes use the
  configured Argon2id driver; tests assert the resulting hash algorithm.
- Session and authentication settings are in Laravel's `config/auth.php`,
  `config/session.php`, `config/hashing.php`, and `config/fortify.php`. The
  application uses database-backed sessions. Production cookies are configured
  as secure by the application; production HTTPS and proxy configuration still
  need to be correct.

## MFA and sessions

The `RequirePrivilegedMfa` middleware requires confirmed MFA for the owner and
any staff member with a configured privileged permission or permission group.
The security setup and recovery routes remain reachable so an account can
enroll. Session revocation is not available to an owner until MFA is enabled.
Users can view their own sessions and revoke another session; revoking all
other sessions requires the current password. Staff suspension revokes that
staff member's database sessions. Fortify recovery codes are single-use.

MFA is enforced by application middleware, not by a hardware security key or
an external identity provider. Store recovery codes securely and never share
them with support staff.

## Operator checklist

1. Set a unique production `APP_KEY`; protect it because encrypted application
   values and sessions depend on it.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, a correct HTTPS `APP_URL`, and
   configure Laravel's secure session cookie settings. Terminate HTTPS at a
   trusted proxy and set only explicit trusted proxy addresses. Wildcard proxy
   trust is rejected.
3. Configure database-backed sessions and a private email transport. Verify
   password-reset and invitation delivery using controlled accounts before
   onboarding staff.
4. Provision the owner from a trusted interactive terminal. Do not place
   credentials in shell history, source control, logs, or deployment command
   arguments. Require the owner to enroll and confirm MFA before ordinary use.
5. Review staff access promptly when duties change. Suspend departing staff;
   do not rely only on changing a shared password.
6. Configure backups for the database and protect the encryption key material
   separately. Follow [backup and restore](../operations/backup-and-restore.md).

## Verification and limits

`tests/Feature/Auth/AccountSecurityTest.php` covers Argon2id hashing, owner and
privileged-role MFA, MFA setup and recovery-code login, signed email
verification, per-user session listing/revocation, and password-protected
revocation of other sessions. Staff lifecycle tests cover suspension and
session revocation; invitation tests cover invitation lifecycle and
authorization. These are local automated tests. They do not establish that
production mail, TLS, cookie behavior behind the real proxy, password recovery,
or MFA on a deployed host is operational. No production penetration test is
claimed.
