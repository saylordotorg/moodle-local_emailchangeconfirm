# Changelog

All notable changes to this project are documented in this file.

## 1.0.1 - 2026-06-17

### Fixed
- Moved primary email-change interception to Moodle's `core_user\hook\before_user_updated` hook.
- Suppressed Moodle's native new-email confirmation message until old-email verification completes.
- Invalidated native `core_user/email_change` keys when an old-email verification request is created or cancelled.
- Migrated the pending-request notice from the legacy `before_footer` callback to `core\hook\output\before_footer_html_generation`.

### Removed
- Removed the incomplete MFA setting/helper/schema footprint so administrators are not offered a control that is not enforced.

## 1.0.0 - 2026-06-12

### Added
- Initial release.
- Old-email verification before the core new-email confirmation flow.
- Interception via the `\core\event\user_updated` observer.
- Configurable verification window (5-1440 minutes, default 30).
- Configurable maximum failed attempts (1-10, default 3).
- Security completion notification sent to the old email address.
- Pending-request cancellation from the profile edit page.
- Scheduled cleanup task for expired requests.
- Audit events: requested, old-email verified, cancelled, completed, token validation failed.
- Privacy (GDPR) provider for export and deletion.
- Moodle Plugin CI workflow.
