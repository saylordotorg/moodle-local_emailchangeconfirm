<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_emailchangeconfirm;

defined('MOODLE_INTERNAL') || die();

/**
 * Integration tests for the observer and the full email-change flow.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_emailchangeconfirm\observer
 */
final class observer_test extends \advanced_testcase {

    /**
     * Enable the plugin and core confirmation before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('emailchangeconfirmation', 1);
        set_config('enabled', 1, 'local_emailchangeconfirm');
        set_config('verification_window', 30, 'local_emailchangeconfirm');
        set_config('max_attempts', 3, 'local_emailchangeconfirm');
        set_config('notify_completion', 1, 'local_emailchangeconfirm');
        set_config('require_mfa', 0, 'local_emailchangeconfirm');
    }

    /**
     * Simulate the core flow setting the newemail preference, then fire user_updated.
     *
     * @param \stdClass $user
     * @param string $newemail
     * @return void
     */
    private function trigger_core_email_change(\stdClass $user, string $newemail): void {
        // This mirrors what user/edit.php does before firing user_updated.
        set_user_preference('newemail', $newemail, $user->id);
        set_user_preference('newemailattemptsleft', 3, $user->id);

        $event = \core\event\user_updated::create_from_userid($user->id);
        $event->trigger();
    }

    /**
     * Task 26: full interception flow - request created and core flow cancelled.
     *
     * @return void
     */
    public function test_full_interception_flow(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);

        $this->trigger_core_email_change($user, 'new@example.com');

        $request = manager::get_pending_request($user->id);
        $this->assertNotNull($request, 'A pending request must be created.');
        $this->assertSame('new@example.com', $request->newemail);
        $this->assertSame('old@example.com', $request->oldemail);

        // Core flow must have been suppressed.
        $this->assertSame('', get_user_preferences('newemail', '', $user->id));
    }

    /**
     * Task 27: verification resumes the core flow (recreates newemail pref + key).
     *
     * @return void
     */
    public function test_verification_resumes_core_flow(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);

        // Intercept directly so we control the raw token.
        $request = manager::intercept_email_change($user->id, 'old@example.com', 'new@example.com');
        $this->assertSame('', get_user_preferences('newemail', '', $user->id));

        manager::mark_verified($request);

        // The core newemail preference and key must be restored.
        $this->assertSame('new@example.com', get_user_preferences('newemail', '', $user->id));
        $this->assertTrue($DB->record_exists('user_private_key',
            ['script' => 'core_user/email_change', 'userid' => $user->id]));
        $this->assertSame('verified', $DB->get_record(manager::TABLE, ['id' => $request->id])->status);
    }

    /**
     * Task 28: completion notification fires when the email actually changes.
     *
     * @return void
     */
    public function test_completion_detection(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);

        // Set up a verified request awaiting completion.
        $request = manager::intercept_email_change($user->id, 'old@example.com', 'new@example.com');
        manager::mark_verified($request);

        // Simulate core completing the change: email is now the new address.
        $DB->set_field('user', 'email', 'new@example.com', ['id' => $user->id]);

        $sink = $this->redirectEvents();
        $freshuser = $DB->get_record('user', ['id' => $user->id]);
        $event = \core\event\user_updated::create_from_userid($user->id);
        $event->trigger();

        $events = $sink->get_events();
        $sink->close();

        $completed = false;
        foreach ($events as $e) {
            if ($e instanceof event\email_change_completed) {
                $completed = true;
                $this->assertSame('new_email_confirmed', $e->other['reason']);
            }
        }
        $this->assertTrue($completed, 'A completion event must be fired.');

        // The request record must be cleaned up.
        $this->assertFalse($DB->record_exists(manager::TABLE, ['id' => $request->id]));
    }

    /**
     * Task 29: cancellation cleans up all artefacts.
     *
     * @return void
     */
    public function test_cancellation_cleanup(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);
        $request = manager::intercept_email_change($user->id, 'old@example.com', 'new@example.com');

        manager::cancel_request($request->id, 'user_cancelled');

        $this->assertNull(manager::get_pending_request($user->id));
        $this->assertSame('', get_user_preferences(manager::PREF_PENDING, '', $user->id));
        $this->assertSame('', get_user_preferences('newemail', '', $user->id));
    }

    /**
     * Task 30: every fired event carries the required payload fields.
     *
     * @return void
     */
    public function test_event_payloads(): void {
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);

        $sink = $this->redirectEvents();
        $request = manager::intercept_email_change($user->id, 'old@example.com', 'new@example.com');
        manager::validate_token($user->id, 'wrong' . str_repeat('0', 35)); // token_validation_failed.
        manager::mark_verified($request); // old_email_verified.

        $events = $sink->get_events();
        $sink->close();

        $plugins = 0;
        foreach ($events as $e) {
            if (strpos($e->eventname, 'local_emailchangeconfirm') === false) {
                continue;
            }
            $plugins++;
            $this->assertArrayHasKey('oldemail', $e->other);
            $this->assertArrayHasKey('newemail', $e->other);
            $this->assertArrayHasKey('reason', $e->other);
            $this->assertNotEmpty($e->other['oldemail']);
            $this->assertNotEmpty($e->other['newemail']);
            $this->assertNotEmpty($e->other['reason']);
            $this->assertNotEmpty($e->relateduserid);
        }
        $this->assertGreaterThanOrEqual(3, $plugins, 'Expected at least requested, failed and verified events.');
    }

    /**
     * Task 31: a user with moodle/user:update is not intercepted.
     *
     * @return void
     */
    public function test_admin_bypass(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);

        // Grant the capability at system level via the admin role assignment.
        $this->setAdminUser();
        $admin = $DB->get_record('user', ['id' => get_admin()->id]);
        set_user_preference('newemail', 'newadmin@example.com', $admin->id);

        $event = \core\event\user_updated::create_from_userid($admin->id);
        $event->trigger();

        // Admins have moodle/user:update, so no interception request should be created.
        $this->assertSame(0, $DB->count_records(manager::TABLE, ['userid' => $admin->id]));
    }
}
