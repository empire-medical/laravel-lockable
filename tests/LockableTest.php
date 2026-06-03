<?php

namespace LowerRockLabs\Lockable\Tests;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use LowerRockLabs\Lockable\Events\ModelWasLocked;
use LowerRockLabs\Lockable\Events\ModelWasUnlocked;
use LowerRockLabs\Lockable\Models\ModelLockWatcher;
use LowerRockLabs\Lockable\Tests\Models\Admin;
use LowerRockLabs\Lockable\Tests\Models\Note;
use LowerRockLabs\Lockable\Tests\Models\User;
use PHPUnit\Framework\Attributes\Test;

class LockableTest extends TestCase
{
    #[Test]
    public function migrationsContainsModelLocksTable()
    {
        $this->assertTrue(Schema::hasColumn('model_locks', 'user_id'));
    }

    #[Test]
    public function migrationsContainsModelLockWatcheresTable()
    {
        $this->assertTrue(Schema::hasColumn('model_lock_watchers', 'model_lock_id'));
    }

    #[Test]
    public function migrationsContainsNotesTable()
    {
        $this->assertTrue(Schema::hasColumn('notes', 'title'));
    }

    #[Test]
    public function migrationsContainsUsersTable()
    {
        $this->assertTrue(Schema::hasColumn('users', 'name'));
    }

    #[Test]
    public function migrationsContainsAdminsTable()
    {
        $this->assertTrue(Schema::hasColumn('admins', 'name'));
    }

    #[Test]
    public function canCreateAUser()
    {
        // given a user
        $user = User::factory()->create();
        $user->update(['name' => 'Test User 1']);
        $user->save();

        Auth::login($user);

        $this->assertEquals('Test User 1', $user->name);
    }

    #[Test]
    public function canCreateAnAdmin()
    {
        // given a user
        $admin = Admin::factory()->create();
        $admin->update(['name' => 'Test Admin 1']);
        $admin->save();

        Auth::login($admin);

        $this->assertEquals('Test Admin 1', $admin->name);
    }

    #[Test]
    public function canCreateANoteAndObtainLock()
    {
        $user2 = User::factory()->create();
        $user2->update(['name' => 'Test User 2']);
        $user2->save();
        Auth::login($user2);

        $note = Note::factory()->create();

        $lock = $note->lockable()->firstOrNew();
        $lock->user_id = Auth::id();
        $lock->expires_at = Carbon::now()->addSeconds('3600');
        $lock->save();

        $note->update(['title' => 'Test Note 1']);
        $note->save();

        $this->assertEquals('Test Note 1', $note->title);
    }

    #[Test]
    public function canCreateNoteRelinquishLock()
    {
        $user2 = User::factory()->create();
        $user2->update(['name' => 'Test User 2']);
        $user2->save();

        $user3 = User::factory()->create();
        $user3->update(['name' => 'Test User 3']);
        $user3->save();

        $this->assertModelExists($user2);
        $this->assertModelExists($user3);

        Auth::login($user2);
        $note = Note::factory()->create();
        $note->update(['title' => 'Test Note 1']);
        $note->save();

        $noteid = $note->id;
        $this->assertModelExists($note);
        $note->releaseLock();

        Auth::login($user3);
        $note2 = Note::find($noteid);
        if (! $note2->isLocked()) {
            $note2->update(['title' => 'Test Note 3']);
            $note2->save();
        }
        $this->assertEquals('Test Note 3', $note2->title);
    }

    #[Test]
    public function canCreateANoteObtainLockAndRequest()
    {
        $user2 = User::factory()->create();
        $user2->update(['name' => 'Test User 2']);
        $user2->save();
        Auth::login($user2);

        $note = Note::factory()->create();
        $lock = $note->lockable()->firstOrNew();
        $lock->user_id = Auth::id();
        $lock->user_type = get_class(Auth::user());

        $lock->expires_at = Carbon::now()->addSeconds('3600');
        $lock->save();

        $user3 = User::factory()->create();
        $user3->update(['name' => 'Test User 3']);
        $user3->save();
        $note->refresh();
        Auth::login($user3);
        $note->requestLock($user3);

        $lockWatchUser = $note->lockable->lockWatcherUsers->first();

        $mlw = ModelLockWatcher::first();
        $this->assertNotNull($mlw->user->name);
        $this->assertNotNull($mlw->modelLock->lockable->title);
        $this->assertEquals($user3->id, $lockWatchUser->id);
    }

    #[Test]
    public function canCreateANoteAndObtainLockAsAdmin()
    {
        $admin = Admin::factory()->create();
        $admin->update(['name' => 'Test Admin 2']);
        $admin->save();
        Auth::login($admin);

        $note = Note::factory()->create();

        $lock = $note->lockable()->firstOrNew();
        $lock->user_id = Auth::id();
        $lock->user_type = get_class($admin);
        $lock->expires_at = Carbon::now()->addSeconds('3600');
        $lock->save();
        $note->update(['title' => 'Test Note 1']);
        $note->save();
        $this->assertEquals('Test Note 1', $note->title);
        $this->assertEquals($lock->user_type, get_class($admin));
    }

    #[Test]
    public function testNotLockedForSameUser()
    {
        $user1 = User::factory()->create();

        Auth::login($user1);

        $note = Note::factory()->create();
        $note->acquireLock();

        $this->assertFalse($note->isLocked());
    }

    #[Test]
    public function testIsLockedForAnotherUser()
    {
        $user1 = User::factory()->create();
        $user1->update(['name' => 'Creator']);
        $user1->save();

        $user2 = User::factory()->create();
        $user2->update(['name' => 'Searcher']);
        $user2->save();

        $this->assertModelExists($user1);
        $this->assertModelExists($user2);

        Auth::login($user1);

        $note = Note::factory()->create();
        $note->update(['title' => 'Test Note 1']);
        $note->save();
        $lock = $note->lockable()->firstOrNew();
        $user1id = Auth::id();
        $lock->user_id = Auth::id();
        $lock->user_type = get_class(Auth::user());
        $lock->expires_at = Carbon::now()->addSeconds('3600');
        $lock->save();
        $this->assertModelExists($note);
        $noteid = $note->id;

        Auth::login($user2);
        $note2 = Note::find($noteid);
        $lock2 = $note2->lockable()->firstOrNew();

        $this->assertTrue($note2->isLocked());
        $this->assertEquals($lock2->user_id, $user1id);
    }

    #[Test]
    public function testCanAccessModelWithoutLock()
    {
        $user1 = User::factory()->create();
        Auth::login($user1);

        $note = Note::factory()->create(['title' => 'Test Note No Events']);

        $user2 = User::factory()->create();
        Auth::login($user2);
        $note->acquireLock();
        $note->update(['title' => 'Test Note 9']);
        $note->save();

        $this->assertEquals('Test Note 9', $note->title);
    }

    #[Test]
    public function testLockRemovalAfterExpiryAllowsAccess()
    {
        $user1 = User::factory()->create();
        Auth::login($user1);

        $note = Note::factory()->create();
        $lock = $note->lockable()->firstOrNew();
        $lock->user_id = Auth::id();
        $lock->user_type = get_class(Auth::user());
        $lock->expires_at = Carbon::now()->subSeconds('3600');
        $lock->save();

        $user2 = User::factory()->create();
        Auth::login($user2);

        $this->assertFalse($note->isLocked());
    }

    #[Test]
    public function testLockedModelReturnsFalseWhenUpdating()
    {
        $this->expectExceptionMessage('User does not hold the lock to this model.');
        $user3 = User::factory()->create();
        $user3->update(['name' => 'Test User 2']);
        $user3->save();
        Auth::login($user3);

        $note = Note::factory()->create();
        $note->acquireLock();

        $note->update(['title' => 'Test Note 1']);
        $note->save();

        $user4 = User::factory()->create();
        $user4->update(['name' => 'Test User 3']);
        $user4->save();

        Auth::login($user4);
        $note->update(['title' => 'Test Note 4']);
        $note->save();
    }

    #[Test]
    public function testLockDurationIsConfigurablePerModel()
    {
        $user1 = User::factory()->create();
        Auth::login($user1);

        $note = Note::factory()->create();
        $note->modelLockDuration = '8000';
        $note->acquireLock();
        $this->assertTrue(Carbon::now()->addSeconds('4000')->lte($note->lockable->expires_at));
    }

    #[Test]
    public function testEventModelWasLocked()
    {
        Event::fake();

        $user1 = User::factory()->create();
        Auth::login($user1);

        $note = Note::factory()->create();
        $note->acquireLock();
        $note->update(['title' => 'Test Note 4']);
        $note->save();
        $note->releaseLock();
        Event::assertDispatched(ModelWasLocked::class);
    }

    #[Test]
    public function testEventModelWasUnlocked()
    {
        Event::fake();

        $user1 = User::factory()->create();
        Auth::login($user1);

        $note = Note::factory()->create();
        $note->acquireLock();
        $note->releaseLock();
        Event::assertDispatched(ModelWasUnlocked::class);
    }

    #[Test]
    public function testFlushExpiredLocks()
    {
        $user1 = User::factory()->create();
        Auth::login($user1);

        $note = Note::factory()->create();
        $note->update(['title' => 'Test Note 76']);
        $note->save();
        $note->acquireLock();

        $note2 = Note::factory()->create();
        $note2->update(['title' => 'Test Note 999']);
        $note2->save();
        $lock = $note2->lockable()->firstOrNew();
        $lock->user_id = Auth::id();
        $lock->user_type = get_class(Auth::user());
        $lock->expires_at = Carbon::now()->subSeconds('9000');
        $lock->save();

        $this->artisan('locks:flushexpired')->assertExitCode(0);
    }

    #[Test]
    public function testFlushAllLocks()
    {
        $user1 = User::factory()->create();
        Auth::login($user1);

        $note = Note::factory()->create();
        $note->update(['title' => 'Test Note 4']);
        $note->save();
        $note->acquireLock();
        $this->artisan('locks:flushall')->assertExitCode(0);
    }
}
