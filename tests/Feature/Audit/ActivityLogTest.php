<?php

namespace Tests\Feature\Audit;

use App\Enums\ActivityChannel;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    public function test_creating_a_model_is_logged_with_the_author_frozen_and_no_secret(): void
    {
        $this->syncPermissions();
        $author = $this->superadmin(['name' => 'Moustapha']);
        $this->actingAs($author);

        $user = User::factory()->administrateur()->create(['phone' => '70 00 00 99']);

        $log = ActivityLog::where('action', 'users.created')->where('subject_id', $user->id)->firstOrFail();
        $this->assertSame($author->id, $log->user_id);
        $this->assertSame('Moustapha', $log->user_name);
        $this->assertSame('superadmin', $log->user_role);
        $this->assertTrue($log->isBySuperadmin());
        $this->assertSame('user', $log->subject_type, 'alias morph stable');
        $this->assertSame($user->name, $log->subject_label);
        $this->assertSame(ActivityChannel::Web, $log->channel);
        $this->assertSame('+22370000099', $log->new_values['phone']);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
        $this->assertStringContainsString('Création', $log->description);
    }

    public function test_updating_logs_only_the_changed_fields_with_old_and_new_values(): void
    {
        $user = User::factory()->create(['name' => 'Avant']);

        $user->update(['name' => 'Après']);

        $log = ActivityLog::where('action', 'users.updated')->where('subject_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame(['name' => 'Avant'], $log->old_values);
        $this->assertSame(['name' => 'Après'], $log->new_values);
    }

    public function test_a_password_change_is_logged_without_the_password(): void
    {
        $user = User::factory()->create();
        $count = ActivityLog::count();

        $user->update(['password' => 'Nouveau-Secret-9', 'name' => 'Renommé']);

        $log = ActivityLog::where('action', 'users.updated')->latest('id')->firstOrFail();
        $this->assertSame(['name'], array_keys($log->new_values));
        $this->assertSame($count + 1, ActivityLog::count());
    }

    public function test_technical_only_changes_write_nothing(): void
    {
        $user = User::factory()->create();
        $count = ActivityLog::count();

        $user->update(['last_login_at' => now()]);
        $user->touch();

        $this->assertSame($count, ActivityLog::count());
    }

    public function test_soft_delete_and_restore_are_logged(): void
    {
        $user = User::factory()->create();

        $user->delete();
        $user->restore();

        $this->assertSame(['users.created', 'users.deleted', 'users.restored'], ActivityLog::orderBy('id')->pluck('action')->all());
    }

    public function test_the_audit_log_cannot_be_modified_or_deleted(): void
    {
        $log = app(ActivityLogger::class)->log('demo.done', 'demo', 'Action de démonstration');

        try {
            $log->update(['description' => 'falsifiée']);
            $this->fail('update interdit');
        } catch (LogicException) {
            $this->assertSame('Action de démonstration', $log->fresh()->description);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_console_entries_have_no_ip_and_failed_logins_have_no_author(): void
    {
        $log = app(ActivityLogger::class)->log('auth.login_failed', 'auth', 'Échec de connexion', channel: ActivityChannel::Console);

        $this->assertNull($log->user_id);
        $this->assertNull($log->user_name);
        $this->assertNull($log->ip_address);
        $this->assertSame(ActivityChannel::Console, $log->channel);
    }

    public function test_sensitive_keys_are_removed_at_any_depth(): void
    {
        $clean = app(ActivityLogger::class)->sanitize(['name' => 'ok', 'password' => 'x', 'nested' => ['token' => 'y', 'remember_token' => 'z', 'keep' => 1]]);

        $this->assertSame(['name' => 'ok', 'nested' => ['keep' => 1]], $clean);
    }
}
