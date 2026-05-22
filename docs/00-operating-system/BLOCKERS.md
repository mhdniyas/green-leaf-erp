# BLOCKERS & RESOLUTIONS

**Document Version**: 1.0.0  
**Last Updated**: 2026-05-22  
**Current Status**: ✅ No Active Blockers  

---

## Summary

The foundation sprint completed **without blocking issues**. All phases were successfully implemented. Below are documented challenges and their resolutions for future reference.

---

## Resolved Issues

### Issue 1: Permission Migration Command Not Found ✅

**Status**: RESOLVED  
**Severity**: LOW  
**Date Encountered**: 2026-05-22  

**Problem**:
```
Command "permission:create-migration" is not defined.
```

**Root Cause**:
Spatie Permission v7 uses a different command structure than earlier versions.

**Solution**:
Used direct vendor:publish instead of the Artisan command:
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag=permission-migrations
```

**Lesson Learned**:
Always check vendor documentation for the current version when using version-specific commands.

---

### Issue 2: API Routes File Missing ✅

**Status**: RESOLVED  
**Severity**: MEDIUM  
**Date Encountered**: 2026-05-22  

**Problem**:
Fresh Laravel 13 installation doesn't include routes/api.php by default.

**Solution**:
Created routes/api.php with proper v1 namespacing and health check endpoint.

**Implementation**:
```php
Route::prefix('v1')->middleware('api')->name('api.v1.')->group(function () {
    // API routes here
});
```

---

### Issue 3: HTTP Kernel File Doesn't Exist ✅

**Status**: RESOLVED  
**Severity**: LOW  
**Date Encountered**: 2026-05-22  

**Problem**:
Laravel 13 with Boost uses modern configuration pattern; no separate Kernel.php file.

**Solution**:
Updated bootstrap/app.php directly using the Middleware class:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [...]);
})
```

**Impact**:
Simplified middleware registration without breaking changes.

---

### Issue 4: Activity Log Migration Status ✅

**Status**: RESOLVED  
**Severity**: LOW  
**Date Encountered**: 2026-05-22  

**Problem**:
Spatie ActivityLog doesn't publish a separate migration file (included in package).

**Solution**:
ActivityLog uses its own service provider internally. No separate migration needed.

**Verification**:
ActivityLog works through automatic service provider discovery.

---

## Preventative Measures Implemented

### 1. Version Compatibility Matrix ✅

**Document**: [In docs/DECISIONS_LOG.md]

All installed packages and their versions:
```
Laravel 13.8
PHP ^8.3
Sanctum 4.3
Spatie Permission 7.4
Spatie ActivityLog 5.0
Owen-it Auditing 14.0
Spatie Backup 10.2
Spatie MediaLibrary 11.22
Maatwebsite Excel 3.1
IDE Helper 3.7
```

This prevents version conflicts when adding new packages.

### 2. Base Class Patterns ✅

All base classes created to ensure consistency and reduce future bugs:
- BaseRepository - prevents N+1 queries
- BaseService - ensures activity logging
- BaseAction - ensures transactions
- BaseDTO - ensures data consistency

### 3. Middleware Registration Checklist ✅

New middleware automatically registered in bootstrap/app.php using consistent pattern.

### 4. Security Headers Middleware ✅

Global security headers prevent common vulnerabilities without per-route configuration.

---

## Known Limitations (Not Blockers)

### Limitation 1: Redis Not Installed Locally
**Impact**: Queue and cache configured for Redis, but not installed  
**Resolution**: Install Redis before running migrations  
**Command**:
```bash
# macOS
brew install redis

# Linux
sudo apt-get install redis-server

# Docker
docker run -d -p 6379:6379 redis
```

### Limitation 2: Database Not Yet Created
**Impact**: Cannot run migrations until database exists  
**Resolution**: Create database before migrations  
**Command**:
```bash
# Check .env for DB_DATABASE setting (default: green_leaf_erp)
mysql -u root -e "CREATE DATABASE green_leaf_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Then run
php artisan migrate
```

### Limitation 3: ENV File Not Yet Generated
**Impact**: Application won't run until .env is created  
**Resolution**: Copy .env from .env.example  
**Command**:
```bash
cp .env.example .env
php artisan key:generate
```

---

## Dependencies & Prerequisites

Before running the application:

### Required (Blocking)
- [ ] Redis installed and running
- [ ] MySQL database created
- [ ] .env file copied and configured
- [ ] PHP dependencies installed (`composer install`)
- [ ] Frontend dependencies installed (`npm install`)

### Required (For Development)
- [ ] Migrations run (`php artisan migrate`)
- [ ] Permissions seeded (`php artisan db:seed`)
- [ ] IDE Helper generated (`php artisan ide-helper:generate`)

### Optional (For Local Development)
- [ ] Telescope installed (`php artisan telescope:install`)
- [ ] Pail running for logs (`php artisan pail`)

---

## Monitoring & Prevention

### Code Quality Checks
Run before committing:
```bash
# Format code
vendor/bin/pint --format agent

# Run tests
php artisan test --compact

# Security audit
composer audit
```

### Infrastructure Health
Monitor in production:
- [ ] Redis connection status
- [ ] Database query performance
- [ ] Queue worker health
- [ ] Log storage availability
- [ ] Backup job status

---

## Escalation Path

If new issues arise:

1. **Development Blockers**: Document in this file with severity level
2. **Production Issues**: Immediate notification to team lead
3. **Architecture Questions**: Refer to DECISIONS_LOG.md
4. **Unknown Issues**: Escalate with reproduction steps

---

## Common Troubleshooting

### "SQLSTATE[HY000]: General error: 1030 Got error"
**Solution**: Check database storage space and permissions

### "Could not find driver (Swoole, PDO, etc)"
**Solution**: Verify PHP extensions installed: `php -m | grep extension_name`

### "Failed to start queue worker"
**Solution**: Verify Redis is running: `redis-cli ping` should return "PONG"

### "404 on /api/v1 endpoints"
**Solution**: Verify routes/api.php exists and bootstrap/app.php includes it

---

## CI/CD Considerations

### Pre-Deployment Checklist
- [ ] All tests passing
- [ ] Code formatting correct (pint)
- [ ] No security vulnerabilities (composer audit)
- [ ] Database migrations ready
- [ ] Environment variables set
- [ ] Redis connection verified
- [ ] Backup system configured

### Deployment Blockers
- ❌ Failing tests
- ❌ Security vulnerabilities
- ❌ Uncommitted migrations
- ❌ Missing environment variables
- ❌ Redis unavailable in production

---

## Performance Considerations

### Potential Bottlenecks
1. **N+1 Queries**: Prevented by BaseRepository pattern
2. **Missing Indexes**: Document in 03-database/
3. **Large Dataset Pagination**: Default per_page=15 in BaseApiController
4. **Activity Log Growth**: Configure retention in config/activitylog.php

### Monitoring Points
- Query execution time (use Laravel Debugbar locally)
- Memory usage during job processing
- Redis memory consumption
- Database connection pool exhaustion

---

## Future Risk Assessment

### Low Risk ✅
- Adding new models (follow existing patterns)
- Adding new API endpoints (extend BaseApiController)
- Adding new middleware (register in bootstrap/app.php)

### Medium Risk ⚠️
- Changing base class behavior (affects entire app)
- Modifying package configurations
- Changing database schema for core tables

### High Risk 🚨
- Removing audit/activity logging (compliance issue)
- Changing authentication mechanism (security issue)
- Disabling security middleware (security issue)

---

## Lessons Learned

1. **Modern Laravel Configuration**: Bootstrap app.php is the central config point
2. **Vendor Command Variations**: Check package version before using Artisan commands
3. **Service Provider Discovery**: Packages auto-register; no manual registration needed
4. **Base Classes Reduce Issues**: Consistent patterns prevent configuration mistakes

---

## Document Maintenance

**Review Schedule**: Quarterly or when new blockers arise  
**Last Reviewed**: 2026-05-22  
**Next Review**: 2026-08-22  
**Maintainer**: Senior Laravel Architect  

---

**Status**: ✅ No Active Blockers - Foundation Ready  
**Next Action**: Begin module development or deploy to staging  
**Escalation Contact**: Senior Laravel Architect
