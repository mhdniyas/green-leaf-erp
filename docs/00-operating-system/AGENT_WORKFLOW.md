# AGENT WORKFLOW GUIDE

**Quick Reference for Development Agents**

---

## 🎯 PRIMARY DIRECTIVE

When working on this project:

1. **Always read FIRST**: docs/00-operating-system/PROJECT_CONTEXT.md
2. **Check status**: docs/00-operating-system/PROJECT_STATUS.md
3. **Review decisions**: docs/00-operating-system/DECISIONS_LOG.md

---

## 📋 QUICK START

### Before Coding
```bash
# Understand the architecture
cat docs/00-operating-system/PROJECT_CONTEXT.md

# Check what's already done
cat docs/00-operating-system/PROJECT_STATUS.md

# Run the development environment
npm run dev
```

### When Adding Features
```bash
# 1. Create Model (if needed)
php artisan make:model ModuleName

# 2. Create Repository
app/Repositories/ModuleRepository.php
// Extend BaseRepository

# 3. Create Service
app/Services/ModuleService.php
// Extend BaseService

# 4. Create Action (if one-off)
app/Actions/ModuleAction.php
// Extend BaseAction

# 5. Create Controller
app/Http/Controllers/Api/ModuleController.php
// Extend BaseApiController

# 6. Format Code
vendor/bin/pint --format agent

# 7. Create Tests
php artisan make:test ModuleTest

# 8. Run Tests
php artisan test tests/Feature/ModuleTest.php
```

---

## 🏗️ ARCHITECTURE LAYERS

```
Controller (Thin - Validation + Response Only)
    ↓
Service/Action (Business Logic + Transactions)
    ↓
Repository (Database Access Only)
    ↓
Model (Data + Relationships Only)
```

### Rule: Never Break Layers
- ❌ NO queries in controller
- ❌ NO business logic in model
- ❌ NO model relationships in controller
- ✅ Always use repositories
- ✅ Always use services
- ✅ Always validate input

---

## 📁 WHERE TO PUT THINGS

| What | Where |
|------|-------|
| API endpoint | `app/Http/Controllers/Api/` |
| Web page | `app/Http/Controllers/Web/` |
| One-off operation | `app/Actions/` |
| Reusable logic | `app/Services/` |
| Database queries | `app/Repositories/` |
| Data object | `app/DTOs/` |
| Constant | `app/Enums/` |
| Helper function | `app/Helpers/` |
| Custom exception | `app/Exceptions/` |
| API response | `app/Http/Resources/` |
| Shared behavior | `app/Traits/` |
| Interface | `app/Contracts/` |

---

## 🔒 SECURITY CHECKLIST

Before committing code:

- [ ] Uses Form Request validation (never validate in controller)
- [ ] Authorized with Gate/Policy (never skip authorization)
- [ ] Uses repositories (never direct queries)
- [ ] Activity is logged (if modifying data)
- [ ] No secrets in code (check BLOCKERS.md)
- [ ] Security headers enabled (already done globally)
- [ ] CSRF protected (automatic on web routes)
- [ ] XSS prevented (Blade auto-escapes)
- [ ] Mass assignment protected (use $fillable)

---

## 📝 TESTING PATTERN

```php
// tests/Feature/UserTest.php
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_user_can_be_created(): void
    {
        // Arrange
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        
        // Act
        $response = $this->post('/api/v1/users', $data);
        
        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('users', $data);
    }
}
```

---

## 🚀 API ENDPOINT PATTERN

```php
// app/Http/Controllers/Api/UserController.php
use App\Http\Requests\Api\StoreUserRequest;
use App\Services\UserService;
use App\Support\ApiResponse;

class UserController extends BaseApiController
{
    public function __construct(private UserService $service)
    {}

    public function store(StoreUserRequest $request)
    {
        try {
            $user = $this->service->create($request->validated());
            return ApiResponse::success($user, 'User created', 201);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), code: 422);
        }
    }
}
```

---

## 🧪 TEST EXECUTION

```bash
# Run all tests
php artisan test --compact

# Run specific file
php artisan test tests/Feature/UserTest.php

# Run specific test
php artisan test --filter=test_user_can_be_created

# With coverage
php artisan test --coverage
```

---

## 📊 CODE QUALITY

```bash
# Format code (REQUIRED before commit)
vendor/bin/pint --format agent

# Check for issues
vendor/bin/pint --test --format agent

# Security audit
composer audit

# IDE helper update
php artisan ide-helper:generate
php artisan ide-helper:models
```

---

## 🐛 DEBUGGING

```bash
# View logs in real-time
php artisan pail

# Open Tinker REPL
php artisan tinker

# Execute PHP
php artisan tinker --execute 'User::count();'

# Database query profiling
// In browser DevTools → Telescope
```

---

## 📚 IMPORTANT PATTERNS

### Repository Pattern
```php
class UserRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return User::class;
    }
    
    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }
}
```

### Service Pattern
```php
class UserService extends BaseService
{
    public function __construct(private UserRepository $repo)
    {}
    
    public function create(array $data): User
    {
        $user = $this->repo->create($data);
        $this->logActivity('created');
        return $user;
    }
}
```

### Action Pattern
```php
class RegisterUserAction extends BaseAction
{
    public function execute(RegistrationData $data): User
    {
        return $this->transaction(function () use ($data) {
            // Multi-step operation with automatic rollback
        });
    }
}
```

---

## 🚫 COMMON MISTAKES

❌ **DON'T**:
```php
// Direct query in controller
$users = User::all();

// Business logic in model
User::scope()->active();

// No validation
$user = User::create($request->all());

// Fat controller
if ($condition) { /* complex logic */ }

// N+1 queries
foreach ($users as $user) {
    echo $user->posts->count();
}
```

✅ **DO**:
```php
// Via repository
$users = $this->repo->all();

// Via service
$user = $this->service->process($data);

// With validation
$user = $this->service->create($request->validated());

// Thin controller
return $this->service->process($request->validated());

// Eager load
User::with('posts')->get();
```

---

## 🔄 GIT WORKFLOW

```bash
# Create feature branch
git checkout -b feature/user-management

# Format before commit
vendor/bin/pint --format agent

# Run tests
php artisan test --compact

# Commit with message
git add .
git commit -m "feat: add user management"

# Push
git push origin feature/user-management
```

---

## 📞 QUICK REFERENCE

| Need | Command |
|------|---------|
| Create Model | `php artisan make:model User` |
| Create Test | `php artisan make:test UserTest` |
| Create Migration | `php artisan make:migration create_users_table` |
| Create Controller | `php artisan make:controller UserController` |
| Create Request | `php artisan make:request StoreUserRequest` |
| Create Policy | `php artisan make:policy UserPolicy` |
| Create Service | `mkdir app/Services` then create file |
| Create Repository | `mkdir app/Repositories` then create file |
| Format Code | `vendor/bin/pint --format agent` |
| Run Tests | `php artisan test --compact` |
| Show Routes | `php artisan route:list` |
| Database Console | `mysql green_leaf_erp -u root` |

---

## 📖 DOCUMENTATION HIERARCHY

Read in this order:

1. **PROJECT_CONTEXT.md** - Architecture & structure
2. **PROJECT_STATUS.md** - What's implemented
3. **CURRENT_SPRINT.md** - Active work
4. **DECISIONS_LOG.md** - Why things are designed this way
5. **PHASES.md** - Timeline & phases
6. **BLOCKERS.md** - Known issues

---

## 🎓 LEARNING PATH

For new team members:

1. Read PROJECT_CONTEXT.md (30 min)
2. Review DECISIONS_LOG.md (20 min)
3. Look at existing UserRepository, UserService, UserController
4. Follow patterns for new features
5. Ask senior dev if unsure

---

## ⚡ QUICK WINS

Easy first features to implement:

1. Create Profile endpoint
2. Update Profile endpoint
3. Delete Profile endpoint
4. List Users endpoint
5. User Activity endpoint
6. Permission management endpoints
7. Role management endpoints

---

**Version**: 1.0.0  
**Last Updated**: 2026-05-22  
**For Questions**: Refer to PROJECT_CONTEXT.md
