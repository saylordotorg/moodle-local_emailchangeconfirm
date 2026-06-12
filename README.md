# Email Change Confirmation (local_emailchangeconfirm)

[![Moodle Plugin CI](https://github.com/saylordotorg/moodle-local_emailchangeconfirm/actions/workflows/moodle-ci.yml/badge.svg?branch=main)](https://github.com/saylordotorg/moodle-local_emailchangeconfirm/actions/workflows/moodle-ci.yml)

A Moodle 4.5 local plugin that adds **old-email verification** to the email change process.

## The problem

Moodle's built-in email change confirmation (`$CFG->emailchangeconfirmation`) only sends a
confirmation link to the **new** email address. The user's **current** (old) email is never
notified or asked to approve the change. An attacker with session access can therefore change
the account email silently.

## What this plugin does

1. **Intercepts** the email change initiated through the profile edit page.
2. Sends a **verification link to the current (old) email** address. The change does not proceed
   until the user proves ownership of their current address.
3. Once verified, it **resumes Moodle's standard new-email confirmation flow**.
4. Sends a **security notification** to the old address when the change completes.
5. Optionally requires **MFA re-authentication** (via `tool_mfa`) before the change.

The plugin does not modify any core Moodle files. It works entirely through the event observer
system and Moodle's standard user-key, preference and email APIs.

## Requirements

- Moodle 4.5 (2024100700) or later
- `$CFG->emailchangeconfirmation` enabled
- (Optional) `tool_mfa` for MFA re-authentication

## Installation

1. Copy the plugin into `local/emailchangeconfirm` of your Moodle site.
2. Visit **Site administration → Notifications** to run the install.
3. Configure under **Site administration → Security → Email change confirmation**.

## Settings

| Setting | Default | Range |
|---------|---------|-------|
| Enable old-email verification | On | — |
| Verification window (minutes) | 30 | 5–1440 |
| Maximum attempts | 3 | 1–10 |
| Send completion notification | On | — |
| Require MFA re-authentication | Off | — |

## License

GNU GPL v3 or later.
