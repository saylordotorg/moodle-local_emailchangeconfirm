# Changelog

All notable changes to this project are documented in this file.

## 1.0.0 - 2026-06-12

### Added
- Initial release.
- Old-email verification before the core new-email confirmation flow.
- Interception via the `\core\event\user_updated` observer.
- Configurable verification window (5-1440 minutes, default 30).
- Configurable maximum failed attempts (1-10, default 3).
- Security completion notification sent to the old email address.
- Optional MFA re-authentication integration with `tool_mfa`.
- Pending-request cancellation from the profile edit page.
- Scheduled cleanup task for expired requests.
- Audit events: requested, old-email verified, cancelled, completed, token validation failed.
- Privacy (GDPR) provider for export and deletion.
- Moodle Plugin CI workflow.
