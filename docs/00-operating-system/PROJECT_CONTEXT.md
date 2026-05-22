# Enterprise Laravel Foundation - PROJECT CONTEXT

## Overview

This is a **reusable, enterprise-grade Laravel foundation** designed for premium client projects. It's not project-specific; it's a **Laravel operating system** ready for any SaaS, ERP, marketplace, CRM, or logistics application.

## Core Principles

### Production Ready
- Security by default
- Performance optimized
- Scalable architecture
- Enterprise monitoring ready

### Modular
- Clean separation of concerns
- SOLID principles
- DRY, KISS, Clean Architecture
- Easy to extend and maintain

### API Ready
- REST API with versioning
- Sanctum authentication
- Standard response format
- Mobile and web compatible

### Professional Standards
- No shortcuts
- No tutorial-level code
- Audit trails on all actions
- Permission system (RBAC)
- Activity logging

## Tech Stack

| Component | Version | Purpose |
|-----------|---------|---------|
| Laravel | 13.x | Framework |
| PHP | 8.3+ | Runtime |
| MySQL | Latest | Database |
| Redis | Latest | Cache & Queue |
| Sanctum | 4.x | API Auth |
| Spatie Permission | 7.x | RBAC |
| Spatie ActivityLog | 5.x | Activity Tracking |
| Spatie Backup | 10.x | Automated Backups |
| Spatie MediaLibrary | 11.x | Media Management |
| Tailwind | 4.x | Frontend Styling |
| Pest/PHPUnit | 12.x | Testing |

## Core Packages Installed

### Authentication & Authorization
- **laravel/sanctum** - API authentication tokens
- **spatie/laravel-permission** - Role-based access control (RBAC)

### Activity & Audit
- **spatie/laravel-activitylog** - User activity tracking
- **owen-it/laravel-auditing** - Comprehensive audit trails

### Infrastructure
- **spatie/laravel-backup** - Automated backups
- **spatie/laravel-medialibrary** - Media file management
- **maatwebsite/excel** - Excel import/export

### Developer Tools
- **barryvdh/laravel-ide-helper** - IDE intellisense
- **laravel/pint** - Code formatting
- **laravel/telescope** - Local debugging (dev only)

## Architecture Structure

```
app/
├── Actions/          # One-off operations with business logic
├── Services/         # Reusable business logic
├── Repositories/     # Database access layer
├── DTOs/             # Data Transfer Objects
├── Enums/            # Type-safe enumerations
├── Helpers/          # Utility functions
├── Traits/           # Shared model/class behaviors
├── Contracts/        # Interfaces and contracts
├── Policies/         # Authorization policies
├── Exceptions/       # Custom exceptions
├── Jobs/             # Queued jobs
├── Events/           # Domain events
├── Listeners/        # Event listeners
├── Notifications/    # Notifications
├── Queries/          # Complex query builders
├── Domains/          # Business domains
├── Support/          # Internal support classes
├── ValueObjects/     # Immutable value objects
└── Models/           # Eloquent models

routes/
├── web.php          # Web routes
├── api.php          # API routes (v1+)
└── console.php      # Console commands

resources/
├── views/
│   ├── layouts/     # Layout templates
│   ├── components/  # Reusable components
│   ├── pages/       # Page templates
│   └── modules/     # Module-specific views
└── css/
    └── app.css      # Tailwind styles

tests/
├── Feature/         # Feature/integration tests
├── Unit/            # Unit tests
├── Integration/     # API integration tests
├── Architecture/    # Architecture verification
└── Security/        # Security tests

docs/
├── 00-operating-system/  # This documentation
├── 01-prd/               # Product requirements
├── 02-architecture/      # Architecture decisions
├── 03-database/          # Database schema
├── 04-modules/           # Module documentation
├── 05-security/          # Security implementation
└── 06-api/               # API documentation
```

## Code Architecture Rules

### Thin Controllers
Controllers should only:
- Validate input via Form Requests
- Call services/actions
- Return responses

**Never**:
- ❌ Put business logic in controllers
- ❌ Make database queries directly
- ❌ Handle complex operations

### Business Logic in Services/Actions
Services handle complex, reusable business logic.
Actions handle one-off operations.

**Pattern**:
```php
// In Service
public function process(Data $data): Result {
    // Business logic
}

// In Action
public function execute(): Result {
    return $this->transaction(function () {
        // One-off operation with transaction support
    });
}
```

### Database Access via Repositories
Never query directly in controllers or services.

**Pattern**:
```php
class UserRepository extends BaseRepository {
    protected function getModel(): string {
        return User::class;
    }
}
```

### Request Validation
Always use Form Requests for validation.

**Pattern**:
```php
class StoreUserRequest extends FormRequest {
    public function rules(): array {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ];
    }
}
```

### Authorization via Policies
Use policies for all authorization checks.

**Pattern**:
```php
// In Policy
public function update(User $user, Post $post): bool {
    return $user->id === $post->user_id;
}

// In Controller
$this->authorize('update', $post);
```

## Security Foundation

### Authentication
- Session-based for web
- Token-based (Sanctum) for API
- Email verification ready
- MFA structure in place

### Authorization
- Role-based access control (RBAC)
- Permissions system via Spatie
- Policies for resource authorization
- Gates for custom checks

### Validation
- Form Request validation mandatory
- Input sanitization
- File upload validation

### Data Protection
- Mass assignment protected models
- Audit trails on all modifications
- Activity logging for user actions
- Encryption for sensitive data

### API Security
- Rate limiting ready
- API token scopes
- CORS configured
- Request validation

### Production Hardening
- HTTPS enforced
- Security headers (HSTS, CSP, etc.)
- Debug disabled in production
- Secure session configuration

## API Structure

### Base URL
```
GET /api/v1/health
```

### Response Format (Success)
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { /* response data */ },
    "meta": { /* pagination, etc */ }
}
```

### Response Format (Error)
```json
{
    "success": false,
    "message": "Operation failed",
    "errors": { /* validation errors */ },
    "meta": {}
}
```

### Authentication
All authenticated endpoints require:
```
Authorization: Bearer {token}
```

## Development Workflow

### Running Locally
```bash
# Start everything
npm run dev

# This runs:
# - php artisan serve
# - php artisan queue:listen
# - php artisan pail (logs)
# - npm run dev (Vite)
```

### Testing
```bash
# Run all tests
php artisan test --compact

# Run specific test
php artisan test tests/Feature/UserTest.php

# Run with coverage
php artisan test --coverage
```

### Code Quality
```bash
# Format code
vendor/bin/pint

# IDE helper
php artisan ide-helper:generate
php artisan ide-helper:models
```

## Database

### Migrations
Run migrations before development:
```bash
php artisan migrate
```

### Seeders
Seed test data:
```bash
php artisan db:seed
```

### Backups
Automated backups are configured. Check `config/backup.php`.

## Next Steps

1. **Review PHASES.md** - See what's been completed
2. **Review PROJECT_STATUS.md** - Current status
3. **Review CURRENT_SPRINT.md** - Work in progress
4. **Check DECISIONS_LOG.md** - Architecture decisions

---

**Status**: Foundation Ready  
**Last Updated**: 2026-05-22  
**Version**: 1.0.0
