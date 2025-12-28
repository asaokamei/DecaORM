<?php

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\Tests\Users\Container;
use WScore\DecaORM\Tests\Users\Profile;
use WScore\DecaORM\Tests\Users\ProfileRepository;
use WScore\DecaORM\Tests\Users\User;
use WScore\DecaORM\Tests\Users\UserRepository;

class HasOneTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private ProfileRepository $profileRepo;

    protected function setUp(): void
    {
        // In-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table
        $this->pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );

        // Create posts table
        $this->pdo->exec(
            "CREATE TABLE profiles (
            profile_id INTEGER PRIMARY KEY,
            nickname TEXT NOT NULL,
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            FOREIGN KEY (profile_id) REFERENCES users(user_id)
        )"
        );

        // Clear cache before each test
        EntityCache::clear();

        $container = new Container();
        $this->userRepo = new UserRepository($this->pdo, $container);
        $this->profileRepo = new ProfileRepository($this->pdo, $container);
        $container->set(UserRepository::class, $this->userRepo);
        $container->set(ProfileRepository::class, $this->profileRepo);
    }

    public function testCreateUserAndProfile(): void
    {
        // Create a user
        $user = $this->userRepo->createAndSave([
                                                   'name' => 'John Doe',
                                                   'email' => 'john@example.com'
                                               ]);

        $this->assertNotNull($user->getId());
        $this->assertEquals('John Doe', $user->get('name'));

        // Create posts for the user
        $profile = $this->profileRepo->createAndSave([
                                                     'id' => $user->getId(),
                                                     'nickname' => 'Test Taro',
                                                 ]);

        $this->assertNotNull($profile->getId());
        $this->assertEquals($profile->getId(), $user->getId());
    }

    public function testFillProfile(): void
    {
        // Create a user
        $user = $this->userRepo->createAndSave([
                                                   'name' => 'John Doe',
                                                   'email' => 'john@example.com'
                                               ]);
        $profile = $this->profileRepo->createAndSave([
                                                         'id' => $user->getId(),
                                                         'nickname' => 'Test Taro',
                                                     ]);

        $userId = $user->getId();
        EntityCache::clear();

        $user = $this->userRepo->findById($userId);
        $this->userRepo->loadProfile($user);

        $profile = $user->get('profile');
        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertEquals('Test Taro', $profile->get('nickname'));
    }

    public function testFillUserFromProfile(): void
    {
        // Create a user
        $user = $this->userRepo->createAndSave([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);

        // Create profile for the user
        $profile = $this->profileRepo->createAndSave([
            'id' => $user->getId(),
            'nickname' => 'Jane Profile'
        ]);

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
        $loadedUser = $profile->get('user');
        $this->assertInstanceOf(User::class, $loadedUser);
        $this->assertEquals($user->getId(), $loadedUser->getId());
        $this->assertEquals('Jane Doe', $loadedUser->get('name'));
        $this->assertEquals('jane@example.com', $loadedUser->get('email'));

        // Verify bidirectional link - user should have profile set
        $userProfile = $loadedUser->get('profile');
        $this->assertInstanceOf(Profile::class, $userProfile);
        $this->assertEquals($profile->getId(), $userProfile->getId());
        $this->assertEquals('Jane Profile', $userProfile->get('nickname'));
    }

    public function testBatchLoadUserFromProfiles(): void
    {
        // Create multiple users
        $user1 = $this->userRepo->createAndSave([
            'name' => 'User One',
            'email' => 'user1@example.com'
        ]);

        $user2 = $this->userRepo->createAndSave([
            'name' => 'User Two',
            'email' => 'user2@example.com'
        ]);

        // Create profiles
        $profile1 = $this->profileRepo->createAndSave([
            'id' => $user1->getId(),
            'nickname' => 'Profile One'
        ]);

        $profile2 = $this->profileRepo->createAndSave([
            'id' => $user2->getId(),
            'nickname' => 'Profile Two'
        ]);

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
        $profile1User = $profiles[0]->get('user');
        $this->assertInstanceOf(User::class, $profile1User);
        $this->assertEquals($user1->getId(), $profile1User->getId());
        $this->assertEquals('User One', $profile1User->get('name'));

        $profile2User = $profiles[1]->get('user');
        $this->assertInstanceOf(User::class, $profile2User);
        $this->assertEquals($user2->getId(), $profile2User->getId());
        $this->assertEquals('User Two', $profile2User->get('name'));

        // Verify bidirectional links - users should have profiles set
        $user1Profile = $profile1User->get('profile');
        $this->assertInstanceOf(Profile::class, $user1Profile);
        $this->assertEquals($profile1->getId(), $user1Profile->getId());
        $this->assertEquals('Profile One', $user1Profile->get('nickname'));

        $user2Profile = $profile2User->get('profile');
        $this->assertInstanceOf(Profile::class, $user2Profile);
        $this->assertEquals($profile2->getId(), $user2Profile->getId());
        $this->assertEquals('Profile Two', $user2Profile->get('nickname'));
    }

    public function testFillUserFromProfileWithNoUser(): void
    {
        // Create a profile without a user (invalid foreign key)
        $this->pdo->exec("INSERT INTO profiles (profile_id, nickname) VALUES (999, 'Orphan Profile')");
        $profileId = 999;

        $profile = $this->profileRepo->findById($profileId);
        $this->assertNotNull($profile);

        // Try to load user (should handle gracefully)
        $users = $this->profileRepo->load($profile, 'user');

        // Verify return value
        $this->assertCount(0, $users);

        // Should be null if user doesn't exist
        $loadedUser = $profile->get('user');
        $this->assertNull($loadedUser);
    }
}