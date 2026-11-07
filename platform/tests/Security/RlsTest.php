<?php

namespace Tests\Security;

use App\Domain\Audit\Audit;
use App\Domain\Notifications\Notifier;
use App\Support\DbContext;
use App\Support\System;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RLS ayrıca test olunur: tətbiq qatı yan keçilir, birbaşa ms_app rolu ilə SQL işlədilir. */
class RlsTest extends TestCase
{
    public function test_app_role_cannot_bypass_rls_and_is_not_owner(): void
    {
        $role = DB::selectOne('SELECT rolbypassrls, rolsuper FROM pg_roles WHERE rolname = current_user');
        $this->assertFalse($role->rolbypassrls);
        $this->assertFalse($role->rolsuper);
        $owner = DB::selectOne("SELECT tableowner FROM pg_tables WHERE tablename = 'notifications'")->tableowner;
        $this->assertNotSame(DB::selectOne('SELECT current_user AS u')->u, $owner);
    }

    public function test_app_role_cannot_change_schema(): void
    {
        $this->expectException(QueryException::class);
        DB::statement('CREATE TABLE evil (id int)');
    }

    public function test_app_role_cannot_drop_or_alter_tables(): void
    {
        $this->expectException(QueryException::class);
        DB::statement('ALTER TABLE notifications DISABLE ROW LEVEL SECURITY');
    }

    public function test_user_sees_only_own_notifications(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        Notifier::send($a, 'x', [], ['channels' => ['in_app']]);
        Notifier::send($b, 'y', [], ['channels' => ['in_app']]);

        DbContext::actAs($a->id);
        $this->assertSame(['x'], DB::table('notifications')->pluck('event_type')->all());
        // WHERE şərti unudulsa belə B-nin sətri görünmür
        $this->assertSame(0, DB::table('notifications')->where('user_id', $b->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('user_id', $b->id)->update(['read_at' => now()]));

        DbContext::actAs(null);
        $this->assertSame(0, DB::table('notifications')->count(), 'kontekst yoxdursa heç nə görünmür');

        $this->assertSame(2, System::run(fn () => DB::table('notifications')->count()), 'worker rolu hamısını görür');
    }

    public function test_admin_sees_all_via_role_table_not_app_flag(): void
    {
        $admin = $this->makeUser([], ['admin']);
        $u = $this->makeUser();
        Notifier::send($u, 'x', [], ['channels' => ['in_app']]);
        DbContext::actAs($admin->id);
        $this->assertSame(1, DB::table('notifications')->count());
        // Saxta "app.role" parametri heç nə vermir
        DbContext::actAs($u->id + 999);
        DB::select("SELECT set_config('app.role', 'admin', false)");
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_audit_log_is_append_only_even_for_owner(): void
    {
        Audit::record('test.action', null, after: ['a' => 1], entityType: 'x', entityId: 1);
        try {
            DB::table('audit_logs')->update(['action' => 'tampered']);
            $this->fail('ms_app UPDATE edə bilməməlidir');
        } catch (QueryException) {
        }
        try {
            DB::connection('pgsql_owner')->table('audit_logs')->delete();
            $this->fail('owner belə DELETE edə bilməməlidir');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        $this->assertSame(1, DB::table('audit_logs')->count());
    }

    public function test_audit_hash_chain_verifies_and_scrubs_secrets(): void
    {
        Audit::record('a.one', null, after: ['ü' => 'ə', 'b' => ['z' => 1, 'a' => 2]], entityType: 'x', entityId: 1);
        Audit::record('a.two', null, before: ['password' => 'hunter2', 'token' => 'abc'], entityType: 'x', entityId: 2, reason: 'səbəb');
        $this->assertNull(Audit::verifyChain());
        $row = DB::table('audit_logs')->where('action', 'a.two')->first();
        $this->assertStringNotContainsString('hunter2', $row->before);
        $this->assertStringNotContainsString('abc', $row->before);
        $this->artisan('platform:audit:verify')->assertSuccessful();
    }
}
