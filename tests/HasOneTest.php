<?php

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\EntityCache;
use WScore\DecaORM\Tests\Users\Container;
use WScore\DecaORM\Tests\Users\Profile;
use WScore\DecaORM\Tests\Users\ProfileRepository;
use WScore\DecaORM\Tests\Users\UserRepository;

class HasOneTest extends TestCase
{
    private UserRepository $userRepo;
    private ProfileRepository $profileRepo;

    protected function setUp(): void
    {
        // In-memory SQLite database for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table
        $pdo->exec(
            "CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )"
        );

        // Create posts table
        $pdo->exec(
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
        $this->userRepo = new UserRepository($pdo, $container);
        $this->profileRepo = new ProfileRepository($pdo, $container);
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
        $this->userRepo->fillProfile($user);

        $profile = $user->get('profile');
        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertEquals('Test Taro', $profile->get('nickname'));
    }
}