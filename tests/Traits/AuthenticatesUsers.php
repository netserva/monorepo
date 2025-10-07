<?php

namespace Tests\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

trait AuthenticatesUsers
{
    /**
     * Create a test user
     */
    protected function createTestUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ], $attributes));
    }

    /**
     * Create an admin user
     */
    protected function createAdminUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_admin' => true,
        ], $attributes));
    }

    /**
     * Create a super admin user
     */
    protected function createSuperAdminUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Super Admin User',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_admin' => true,
            'is_super_admin' => true,
        ], $attributes));
    }

    /**
     * Authenticate as a test user
     */
    protected function authenticateAsUser(?User $user = null): User
    {
        if (! $user) {
            $user = $this->createTestUser();
        }

        $this->actingAs($user);

        return $user;
    }

    /**
     * Authenticate as an admin user
     */
    protected function authenticateAsAdmin(?User $user = null): User
    {
        if (! $user) {
            $user = $this->createAdminUser();
        }

        $this->actingAs($user);

        return $user;
    }

    /**
     * Authenticate as a super admin user
     */
    protected function authenticateAsSuperAdmin(?User $user = null): User
    {
        if (! $user) {
            $user = $this->createSuperAdminUser();
        }

        $this->actingAs($user);

        return $user;
    }

    /**
     * Authenticate with Sanctum token for API testing
     */
    protected function authenticateWithToken(?User $user = null, array $abilities = ['*']): User
    {
        if (! $user) {
            $user = $this->createTestUser();
        }

        Sanctum::actingAs($user, $abilities);

        return $user;
    }

    /**
     * Create multiple test users with different roles
     */
    protected function createUserHierarchy(): array
    {
        return [
            'super_admin' => $this->createSuperAdminUser([
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
            ]),
            'admin' => $this->createAdminUser([
                'name' => 'Admin',
                'email' => 'admin@example.com',
            ]),
            'user' => $this->createTestUser([
                'name' => 'Regular User',
                'email' => 'user@example.com',
            ]),
            'readonly_user' => $this->createTestUser([
                'name' => 'Read Only User',
                'email' => 'readonly@example.com',
                'can_write' => false,
            ]),
        ];
    }

    /**
     * Test authentication for different user types
     */
    protected function assertCanAccessAsAdmin(string $uri, string $method = 'GET'): void
    {
        $admin = $this->authenticateAsAdmin();

        $response = match ($method) {
            'GET' => $this->get($uri),
            'POST' => $this->post($uri),
            'PUT' => $this->put($uri),
            'PATCH' => $this->patch($uri),
            'DELETE' => $this->delete($uri),
            default => $this->get($uri),
        };

        $response->assertStatus(200);
    }

    /**
     * Test that regular users cannot access admin routes
     */
    protected function assertCannotAccessAsUser(string $uri, string $method = 'GET'): void
    {
        $user = $this->authenticateAsUser();

        $response = match ($method) {
            'GET' => $this->get($uri),
            'POST' => $this->post($uri),
            'PUT' => $this->put($uri),
            'PATCH' => $this->patch($uri),
            'DELETE' => $this->delete($uri),
            default => $this->get($uri),
        };

        $response->assertStatus(403);
    }

    /**
     * Test that unauthenticated users are redirected to login
     */
    protected function assertRequiresAuthentication(string $uri, string $method = 'GET'): void
    {
        $response = match ($method) {
            'GET' => $this->get($uri),
            'POST' => $this->post($uri),
            'PUT' => $this->put($uri),
            'PATCH' => $this->patch($uri),
            'DELETE' => $this->delete($uri),
            default => $this->get($uri),
        };

        $response->assertRedirect('/login');
    }

    /**
     * Test API authentication with tokens
     */
    protected function assertApiRequiresToken(string $uri, array $data = [], string $method = 'GET'): void
    {
        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $data),
            'PUT' => $this->putJson($uri, $data),
            'PATCH' => $this->patchJson($uri, $data),
            'DELETE' => $this->deleteJson($uri, $data),
            default => $this->getJson($uri),
        };

        $response->assertUnauthorized();
    }

    /**
     * Test API access with valid token
     */
    protected function assertApiAccessWithToken(string $uri, array $data = [], string $method = 'GET'): void
    {
        $user = $this->authenticateWithToken();

        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $data),
            'PUT' => $this->putJson($uri, $data),
            'PATCH' => $this->patchJson($uri, $data),
            'DELETE' => $this->deleteJson($uri, $data),
            default => $this->getJson($uri),
        };

        $response->assertSuccessful();
    }

    /**
     * Create user with specific permissions for testing
     */
    protected function createUserWithPermissions(array $permissions): User
    {
        $user = $this->createTestUser();

        // If using a permission system like Spatie Permission
        if (class_exists(\Spatie\Permission\Models\Permission::class)) {
            foreach ($permissions as $permission) {
                if (! $user->hasPermissionTo($permission)) {
                    $user->givePermissionTo($permission);
                }
            }
        } else {
            // Use custom permissions array if no permission package
            $user->update(['permissions' => $permissions]);
        }

        return $user;
    }

    /**
     * Assert user has required permissions
     */
    protected function assertUserHasPermission(User $user, string $permission): void
    {
        if (class_exists(\Spatie\Permission\Models\Permission::class)) {
            $this->assertTrue(
                $user->hasPermissionTo($permission),
                "User does not have permission: {$permission}"
            );
        } else {
            $permissions = $user->permissions ?? [];
            $this->assertContains(
                $permission,
                $permissions,
                "User does not have permission: {$permission}"
            );
        }
    }

    /**
     * Clean up authentication after test
     */
    protected function cleanupAuthentication(): void
    {
        auth()->logout();
        session()->flush();

        if (class_exists(\Laravel\Sanctum\Sanctum::class)) {
            Sanctum::actingAs(null);
        }
    }
}
