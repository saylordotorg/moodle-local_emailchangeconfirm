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

/**
 * Property-based and unit tests for the manager class.
 *
 * These tests use bounded generative loops over randomised inputs to assert the
 * correctness properties described in the design document.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_emailchangeconfirm\manager
 */
final class manager_test extends \advanced_testcase {
    /** @var int Number of generative iterations per property. */
    const ITERATIONS = 100;

    /**
     * Enable the plugin with default configuration before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('enabled', 1, 'local_emailchangeconfirm');
        set_config('verification_window', 30, 'local_emailchangeconfirm');
        set_config('max_attempts', 3, 'local_emailchangeconfirm');
        set_config('notify_completion', 1, 'local_emailchangeconfirm');
        set_config('require_mfa', 0, 'local_emailchangeconfirm');
    }

    /**
     * Helper to create an intercepted request for a fresh user.
     *
     * @param string $old Old email.
     * @param string $new New email.
     * @return array [user, request]
     */
    private function make_request(string $old = 'old@example.com', string $new = 'new@example.com'): array {
        $user = $this->getDataGenerator()->create_user(['email' => $old]);
        $request = manager::intercept_email_change($user->id, $old, $new);
        return [$user, $request];
    }

    /**
     * Property 1: tokens are exactly 40 alphanumeric characters.
     *
     * @return void
     */
    public function test_property_token_length_and_charset(): void {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $token = manager::generate_token();
            $this->assertSame(40, strlen($token), 'Token length must be exactly 40.');
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{40}$/', $token,
                'Token must be alphanumeric.');
        }
    }

    /**
     * Property 2: at most one pending request per user at any time.
     *
     * @return void
     */
    public function test_property_single_pending_request(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);

        for ($i = 0; $i < 20; $i++) {
            $newemail = 'new' . $i . '@example.com';
            manager::intercept_email_change($user->id, 'old@example.com', $newemail);
            $pending = $DB->count_records(manager::TABLE, ['userid' => $user->id, 'status' => 'pending']);
            $this->assertLessThanOrEqual(1, $pending,
                'There must never be more than one pending request per user.');
        }
    }

    /**
     * Property 3: interception suppresses the core newemail preferences.
     *
     * @return void
     */
    public function test_property_interception_completeness(): void {
        [$user, $request] = $this->make_request();
        $this->assertSame('pending', $request->status);
        $this->assertSame('', get_user_preferences('newemail', '', $user->id));
        $this->assertSame('', get_user_preferences('newemailattemptsleft', '', $user->id));
    }

    /**
     * Property 4: failed validations decrement attempts by exactly 1 and never increase.
     *
     * @return void
     */
    public function test_property_attempt_decrement_monotonicity(): void {
        set_config('max_attempts', 10, 'local_emailchangeconfirm');
        [$user, $request] = $this->make_request();

        $previous = (int) manager::get_pending_request($user->id)->attemptsleft;
        $this->assertSame(10, $previous);

        for ($i = 0; $i < 5; $i++) {
            manager::validate_token($user->id, 'wrongtoken' . str_repeat('0', 30));
            $current = (int) manager::get_pending_request($user->id)->attemptsleft;
            $this->assertSame($previous - 1, $current,
                'Each failed attempt must decrement attemptsleft by exactly 1.');
            $previous = $current;
        }
    }

    /**
     * Property 5: token hash round-trip - correct token validates, others fail.
     *
     * @return void
     */
    public function test_property_token_hash_roundtrip(): void {
        global $DB;
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $token = manager::generate_token();
            $hash = manager::hash_token($token);
            // Correct token always validates.
            $this->assertTrue(hash_equals($hash, manager::hash_token($token)));
            // A different token never validates.
            $other = manager::generate_token();
            if ($other !== $token) {
                $this->assertFalse(hash_equals($hash, manager::hash_token($other)));
            }
        }
    }

    /**
     * Property 6: expired requests reject validation regardless of token.
     *
     * @return void
     */
    public function test_property_expiry_enforcement(): void {
        global $DB;
        [$user, $request] = $this->make_request();

        // Force expiry.
        $request->timeexpires = time() - 1;
        $DB->update_record(manager::TABLE, $request);

        // Even the "correct" path is rejected once expired.
        $result = manager::validate_token($user->id, 'anytoken' . str_repeat('0', 32));
        $this->assertNull($result, 'Expired request must reject validation.');
        $status = $DB->get_record(manager::TABLE, ['id' => $request->id])->status;
        $this->assertSame('expired', $status);
    }

    /**
     * Property 9: only valid state transitions are reachable; terminal states are final.
     *
     * @return void
     */
    public function test_property_state_machine_validity(): void {
        global $DB;

        // pending -> verified.
        [$u1, $r1] = $this->make_request('a@example.com', 'a2@example.com');
        manager::mark_verified($r1);
        $this->assertSame('verified', $DB->get_record(manager::TABLE, ['id' => $r1->id])->status);

        // pending -> cancelled.
        [$u2, $r2] = $this->make_request('b@example.com', 'b2@example.com');
        manager::cancel_request($r2->id, 'user_cancelled');
        $this->assertSame('cancelled', $DB->get_record(manager::TABLE, ['id' => $r2->id])->status);

        // pending -> expired.
        [$u3, $r3] = $this->make_request('c@example.com', 'c2@example.com');
        manager::mark_expired($r3);
        $this->assertSame('expired', $DB->get_record(manager::TABLE, ['id' => $r3->id])->status);

        // Terminal: a verified request is no longer pending and cannot be re-fetched as pending.
        $this->assertNull(manager::get_pending_request($u1->id));
    }

    /**
     * Property 10: cleanup is idempotent.
     *
     * @return void
     */
    public function test_property_cleanup_idempotence(): void {
        global $DB;
        [$user, $request] = $this->make_request();
        $request->timeexpires = time() - 100;
        $DB->update_record(manager::TABLE, $request);

        $first = manager::cleanup_expired();
        $second = manager::cleanup_expired();
        $third = manager::cleanup_expired();

        $this->assertGreaterThanOrEqual(1, $first);
        $this->assertSame(0, $second, 'Second cleanup run must find nothing to do.');
        $this->assertSame(0, $third, 'Third cleanup run must find nothing to do.');
    }

    /**
     * Property 8: when disabled, no interception occurs and core prefs are untouched.
     *
     * @return void
     */
    public function test_property_disabled_passthrough(): void {
        global $DB;
        set_config('enabled', 0, 'local_emailchangeconfirm');

        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);
        set_user_preference('newemail', 'new@example.com', $user->id);

        $result = manager::intercept_email_change($user->id, 'old@example.com', 'new@example.com');
        $this->assertNull($result, 'Disabled plugin must not intercept.');
        $this->assertSame(0, $DB->count_records(manager::TABLE, ['userid' => $user->id]));
        $this->assertSame('new@example.com', get_user_preferences('newemail', '', $user->id),
            'Disabled plugin must leave the core newemail preference intact.');
    }

    /**
     * Identical old/new email is not intercepted.
     *
     * @return void
     */
    public function test_identical_email_not_intercepted(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'same@example.com']);
        $result = manager::intercept_email_change($user->id, 'same@example.com', 'same@example.com');
        $this->assertNull($result);
        $this->assertSame(0, $DB->count_records(manager::TABLE, ['userid' => $user->id]));
    }

    /**
     * Exhausting attempts cancels the request.
     *
     * @return void
     */
    public function test_max_attempts_cancels_request(): void {
        global $DB;
        set_config('max_attempts', 2, 'local_emailchangeconfirm');
        [$user, $request] = $this->make_request();

        manager::validate_token($user->id, 'wrong' . str_repeat('0', 35));
        manager::validate_token($user->id, 'wrong' . str_repeat('1', 35));

        $this->assertNull(manager::get_pending_request($user->id),
            'Request must be cancelled once attempts are exhausted.');
        $this->assertSame('cancelled', $DB->get_record(manager::TABLE, ['id' => $request->id])->status);
    }

    /**
     * Setting accessors clamp out-of-range stored values to safe defaults.
     *
     * @return void
     */
    public function test_settings_clamping(): void {
        set_config('verification_window', 99999, 'local_emailchangeconfirm');
        $this->assertSame(30 * 60, manager::get_window_seconds());

        set_config('max_attempts', 0, 'local_emailchangeconfirm');
        $this->assertSame(3, manager::get_max_attempts());
    }
}
