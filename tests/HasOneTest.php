<?php

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\Tests\Fixtures\Relations\Profile;
use WScore\DecaORM\Tests\Fixtures\Relations\ProfileRepository;
use WScore\DecaORM\Tests\Fixtures\Relations\RelationsFixture;
use WScore\DecaORM\Tests\Fixtures\Relations\User;
use WScore\DecaORM\Tests\Fixtures\Relations\UserRepository;

class HasOneTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private ProfileRepository $profileRepo;

    protected function setUp(): void
    {
        $fixture = RelationsFixture::create();
        $this->pdo = $fixture->pdo;
        $this->userRepo = $fixture->users;
        $this->profileRepo = $fixture->profiles;
    }

    public function testCreateUserAndProfile(): void
    {
        // Create a user
        $user = $this->userRepo->create([
                                                   'name' => 'John Doe',
                                                   'email' => 'john@example.com'
                                               ]);
        $this->userRepo->save($user);

        $this->assertNotNull($user->getId());
        $this->assertEquals('John Doe', $user->getRaw('name'));

        // Create posts for the user
        $profile = $this->profileRepo->create([
                                                     'id' => $user->getId(),
                                                     'nickname' => 'Test Taro',
                                                 ]);
        $this->profileRepo->save($profile);

        $this->assertNotNull($profile->getId());
        $this->assertEquals($profile->getId(), $user->getId());
    }

    public function testFillProfile(): void
    {
        // Create a user
        $user = $this->userRepo->create([
                                                   'name' => 'John Doe',
                                                   'email' => 'john@example.com'
                                               ]);
        $this->userRepo->save($user);
        $profile = $this->profileRepo->create([
                                                         'id' => $user->getId(),
                                                         'nickname' => 'Test Taro',
                                                     ]);
        $this->profileRepo->save($profile);

        $userId = $user->getId();
        EntityCache::clear();

        $user = $this->userRepo->findById($userId);
        $this->userRepo->loadProfile($user);

        $profile = $user->getRaw('profile');
        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertEquals('Test Taro', $profile->getRaw('nickname'));
    }

    public function testFillUserFromProfile(): void
    {
        // Create a user
        $user = $this->userRepo->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);
        $this->userRepo->save($user);

        // Create profile for the user
        $profile = $this->profileRepo->create([
            'id' => $user->getId(),
            'nickname' => 'Jane Profile'
        ]);
        $this->profileRepo->save($profile);

        $profileId = $profile->getId();
        EntityCache::clear();

        // Reload profile
        $profile = $this->profileRepo->findById($profileId);
        $this->assertNotNull($profile);

        // Fill user from profile (BelongsToOne)
        $users = $this->profileRepo->load($profile, 'user');

        // Verify return value
        $this->assertCount(1, $users);

        // Verify user is set on profile
        $loadedUser = $profile->getRaw('user');
        $this->assertInstanceOf(User::class, $loadedUser);
        $this->assertEquals($user->getId(), $loadedUser->getId());
        $this->assertEquals('Jane Doe', $loadedUser->getRaw('name'));
        $this->assertEquals('jane@example.com', $loadedUser->getRaw('email'));

        // Verify bidirectional link - user should have profile set
        $userProfile = $loadedUser->getRaw('profile');
        $this->assertInstanceOf(Profile::class, $userProfile);
        $this->assertEquals($profile->getId(), $userProfile->getId());
        $this->assertEquals('Jane Profile', $userProfile->getRaw('nickname'));
    }

    public function testBatchLoadUserFromProfiles(): void
    {
        // Create multiple users
        $user1 = $this->userRepo->create([
            'name' => 'User One',
            'email' => 'user1@example.com'
        ]);
        $this->userRepo->save($user1);

        $user2 = $this->userRepo->create([
            'name' => 'User Two',
            'email' => 'user2@example.com'
        ]);
        $this->userRepo->save($user2);

        // Create profiles
        $profile1 = $this->profileRepo->create([
            'id' => $user1->getId(),
            'nickname' => 'Profile One'
        ]);
        $this->profileRepo->save($profile1);

        $profile2 = $this->profileRepo->create([
            'id' => $user2->getId(),
            'nickname' => 'Profile Two'
        ]);
        $this->profileRepo->save($profile2);

        // Clear cache and reload
        EntityCache::clear();
        $profiles = [
            $this->profileRepo->findById($profile1->getId()),
            $this->profileRepo->findById($profile2->getId())
        ];

        // Batch load users for all profiles
        $users = $this->profileRepo->load($profiles, 'user');

        // Verify return value
       $this->assertCount(2, $users);

        // Verify users are set on profiles
        $profile1User = $profiles[0]->getRaw('user');
        $this->assertInstanceOf(User::class, $profile1User);
        $this->assertEquals($user1->getId(), $profile1User->getId());
        $this->assertEquals('User One', $profile1User->getRaw('name'));

        $profile2User = $profiles[1]->getRaw('user');
        $this->assertInstanceOf(User::class, $profile2User);
        $this->assertEquals($user2->getId(), $profile2User->getId());
        $this->assertEquals('User Two', $profile2User->getRaw('name'));

        // Verify bidirectional links - users should have profiles set
        $user1Profile = $profile1User->getRaw('profile');
        $this->assertInstanceOf(Profile::class, $user1Profile);
        $this->assertEquals($profile1->getId(), $user1Profile->getId());
        $this->assertEquals('Profile One', $user1Profile->getRaw('nickname'));

        $user2Profile = $profile2User->getRaw('profile');
        $this->assertInstanceOf(Profile::class, $user2Profile);
        $this->assertEquals($profile2->getId(), $user2Profile->getId());
        $this->assertEquals('Profile Two', $user2Profile->getRaw('nickname'));
    }

    public function testFillUserFromProfileWithNoUser(): void
    {
        // Create user and profile, then delete user to get an orphan profile (DB-agnostic)
        $user = $this->userRepo->create(['name' => 'Temporary', 'email' => 'tmp@example.com']);
        $this->userRepo->save($user);
        $profileId = $user->getId();
        $this->profileRepo->save($this->profileRepo->create(['id' => $profileId, 'nickname' => 'Orphan Profile']));
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        } elseif ($driver === 'pgsql') {
            $this->pdo->exec('SET session_replication_role = replica');
        }
        $this->pdo->exec("DELETE FROM users WHERE user_id = {$profileId}");
        if ($driver === 'mysql') {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } elseif ($driver === 'pgsql') {
            $this->pdo->exec('SET session_replication_role = DEFAULT');
        }

        $profile = $this->profileRepo->findById($profileId);
        $this->assertNotNull($profile);

        // Try to load user (should handle gracefully)
        $users = $this->profileRepo->load($profile, 'user');

        // Verify return value
        $this->assertCount(0, $users);

        // Should be null if user doesn't exist
        $loadedUser = $profile->getRaw('user');
        $this->assertNull($loadedUser);
    }
}