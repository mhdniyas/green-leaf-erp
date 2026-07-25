Act as a senior Laravel security engineer, application architect, DevOps reviewer, database specialist, and production-readiness auditor.

Perform a complete professional audit of this Laravel production website.

## Project context

* Framework: Laravel
* Environment: Production
* Application type: [Describe the website]
* Laravel version: [Version]
* PHP version: [Version]
* Database: [MySQL/PostgreSQL]
* Server: [Ubuntu/Nginx/Apache/AWS/DigitalOcean/etc.]
* Authentication: [Sanctum/Passport/Session/Auth package]
* Frontend: [Blade/Livewire/Inertia/Vue/React]
* Queue: [Redis/Database/SQS/None]
* Storage: [Local/S3/etc.]
* Payment integrations: [List or “None”]
* External APIs: [List or “None”]

Only inspect and test systems, source code, endpoints, and infrastructure that I own or am explicitly authorized to audit.

## Main objective

Determine whether the project is:

1. Secure against common attacks.
2. Professionally structured.
3. Safe to operate in production.
4. Maintainable and scalable.
5. Correctly configured for Laravel production.
6. Protected against data loss, privilege escalation, financial errors, and unauthorized access.

Do not give a generic checklist only. Inspect the actual implementation, trace important request flows, identify exact files and lines, explain the risk, and provide production-ready fixes.

## Audit methodology

Start by understanding the project before recommending changes.

Inspect:

* `composer.json`
* `composer.lock`
* `.env.example`
* `config/`
* `routes/`
* `app/Http/Controllers/`
* `app/Http/Middleware/`
* `app/Http/Requests/`
* `app/Models/`
* `app/Policies/`
* `app/Providers/`
* `app/Services/`
* `app/Actions/`
* `app/Repositories/`
* `app/Jobs/`
* `app/Listeners/`
* `app/Console/`
* `database/migrations/`
* `database/seeders/`
* `resources/views/`
* `storage/`
* `public/`
* tests
* deployment files
* Nginx or Apache configuration
* queue and scheduler configuration
* backup configuration
* CI/CD workflows

Build a high-level architecture map showing:

* Authentication flow
* Authorization flow
* Main business workflows
* Sensitive data flows
* File-upload flows
* Payment flows
* Admin operations
* Background jobs
* External API integrations
* Database transaction boundaries

## 1. Authentication audit

Check for:

* Secure login implementation
* Password hashing
* Password-reset security
* Email or phone verification
* Brute-force protection
* Login rate limiting
* Session fixation
* Session expiration
* “Remember me” security
* Logout invalidation
* Multi-device session handling
* Token expiration and revocation
* Sanctum or Passport configuration
* API-token leakage
* Authentication bypasses
* Account enumeration
* Weak default credentials
* Inactive, blocked, or deleted user handling
* Two-factor authentication for privileged accounts

Verify that authentication is enforced on every protected route.

## 2. Authorization and access-control audit

Check every route, controller action, API endpoint, Livewire action, job, command, and administrative operation.

Look for:

* Missing policies or gates
* Missing middleware
* Role-only checks without ownership checks
* IDOR vulnerabilities
* Horizontal privilege escalation
* Vertical privilege escalation
* Users accessing another user’s records
* Staff accessing another branch, company, shop, tenant, or organization
* Admin-only actions exposed to normal users
* Mass assignment of role, status, owner, balance, price, or approval fields
* Client-controlled permission values
* Hidden UI buttons without backend protection
* Insecure route model binding
* Unauthorized exports, reports, downloads, and attachments

For each sensitive action, confirm both:

1. The user has the required role or permission.
2. The requested record belongs to an organization, account, tenant, branch, shop, or resource that the user may access.

## 3. Input validation and injection audit

Inspect all request inputs, including:

* URL parameters
* Query parameters
* JSON payloads
* Form submissions
* Uploaded files
* CSV or Excel imports
* Search fields
* Sort fields
* Filters
* Webhooks
* Headers
* Command-line arguments

Check for:

* SQL injection
* Raw SQL misuse
* Unsafe `DB::raw()`
* Unsafe dynamic column names
* Command injection
* PHP code execution
* Template injection
* LDAP injection
* Header injection
* Log injection
* Path traversal
* Open redirects
* Server-side request forgery
* XML external entity attacks
* CSV formula injection
* Unsafe unserialization
* Client-controlled class or method names
* Missing Laravel Form Request validation
* Improper use of `validate()`
* Weak validation rules
* Missing maximum lengths
* Unsafe nested arrays
* Numeric overflow
* Negative values where not allowed
* Invalid state transitions
* Date and timezone manipulation

Do not assume Eloquent automatically makes every query safe. Review raw expressions, dynamic fields, joins, order clauses, and report queries carefully.

## 4. Mass-assignment and model-security audit

Review every model for:

* Unsafe `$guarded = []`
* Overly broad `$fillable`
* Sensitive fields accepted directly from requests
* Role or permission changes
* User ownership changes
* Approval status changes
* Price, discount, balance, credit, payment, tax, stock, and cost fields
* Hidden attributes
* Attribute casting
* Encrypted casts
* Password exposure
* API-token exposure
* Model events with unexpected side effects
* Global scopes that can be bypassed
* Soft-delete behavior
* Restore permissions
* Force-delete permissions

Recommend using DTOs, validated data, explicit field mapping, actions, or service classes where appropriate.

## 5. CSRF, XSS, CORS, and browser-security audit

Check for:

* CSRF protection on every state-changing web request
* Incorrect CSRF exemptions
* Unsafe webhook exemptions
* Stored XSS
* Reflected XSS
* DOM-based XSS
* Unsafe `{!! !!}` output
* Unescaped user content in JavaScript
* Unsafe HTML stored in the database
* Rich-text editor sanitization
* Unsafe Blade components
* Content Security Policy
* CORS misconfiguration
* Credentials allowed with wildcard origins
* Clickjacking protection
* MIME sniffing
* Referrer policy
* Permissions policy
* Secure cookies
* `HttpOnly`
* `SameSite`
* HTTPS enforcement
* HSTS

Review frontend JavaScript and Blade templates, not only backend controllers.

## 6. File upload and download audit

Inspect every upload, import, image, document, media, backup, and attachment feature.

Check for:

* MIME type and extension validation
* File-signature validation
* Maximum file size
* Image decompression bombs
* Executable uploads
* SVG XSS
* Double extensions
* Filename traversal
* Public storage of sensitive files
* Predictable filenames
* Unauthorized file downloads
* Missing ownership checks
* Direct-object-reference vulnerabilities
* Malware scanning requirements
* Temporary file cleanup
* EXIF metadata leakage
* Secure signed URLs
* S3 bucket permissions
* Local storage permissions
* Web server execution inside upload directories

Ensure uploaded PHP or executable files can never be executed.

## 7. Business-logic audit

Trace complete business workflows instead of reviewing isolated functions only.

Look for:

* Bypassing approvals
* Replaying requests
* Duplicate submissions
* Race conditions
* Editing records after approval
* Invalid status transitions
* Client-controlled totals
* Client-controlled prices
* Client-controlled discounts
* Quantity manipulation
* Negative values
* Duplicate invoices or payments
* Unauthorized cancellation
* Refund abuse
* Reusing coupons
* Reusing payment references
* Inventory going below zero
* Incorrect stock movements
* Incorrect account balances
* Rounding errors
* Currency errors
* Tax calculation errors
* Overpayment or underpayment
* Partial-payment errors
* Concurrency problems
* Missing database transactions
* Jobs executing twice
* Users approving their own requests
* Audit records that can be modified or deleted

For every important workflow, describe:

1. Expected state transitions.
2. Actual state transitions found in the code.
3. Missing protections.
4. Required database constraints.
5. Required transaction and locking strategy.

## 8. Database audit

Review schema design and database usage.

Check for:

* Missing foreign keys
* Missing unique constraints
* Missing indexes
* Duplicate records
* Orphaned records
* Incorrect nullable columns
* Unsafe cascade deletes
* Money stored as floating-point values
* Incorrect decimal precision
* Timestamp consistency
* Timezone handling
* Soft-delete uniqueness issues
* Tenant-isolation weaknesses
* Missing optimistic or pessimistic locking
* Race conditions
* N+1 queries
* Unbounded queries
* Loading full tables into memory
* Large exports without chunking
* Inefficient reports
* Incorrect transaction boundaries
* Deadlock risks
* Long-running transactions

Identify exact migration changes required.

## 9. API security audit

Check all API endpoints for:

* Authentication
* Authorization
* Rate limiting
* Token scopes
* Token expiry
* Token revocation
* Pagination
* Maximum page size
* Excessive data exposure
* Sensitive fields in resources
* Verbose validation errors
* API versioning
* Replay protection
* Idempotency
* Enumeration attacks
* Predictable identifiers
* Unsafe filtering and sorting
* Missing request-size limits
* Improper HTTP methods
* Incorrect status codes
* Missing webhook verification
* API documentation exposing secrets

Verify that API Resources return only fields the client actually needs.

## 10. Webhook and external-integration audit

For every webhook and third-party API, verify:

* Signature validation
* Timestamp validation
* Replay prevention
* IP allowlisting where appropriate
* Idempotency
* Duplicate-event handling
* Secret storage
* Timeout configuration
* Retry strategy
* Exponential backoff
* Error handling
* Logging without secret leakage
* TLS verification
* SSRF protection
* Safe callback URLs
* Correct event-state validation
* Reconciliation jobs

Never trust payment success values submitted by the frontend.

## 11. Payment and financial-security audit

Where applicable, inspect:

* Payment initiation
* Payment verification
* Callback handling
* Webhook handling
* Payment reconciliation
* Refunds
* Wallet balances
* Credits
* Discounts
* Taxes
* Invoices
* Journal entries
* Account balances

Check for:

* Client-side total manipulation
* Fake transaction IDs
* Duplicate payments
* Replayed callbacks
* Race conditions
* Amount or currency mismatch
* Payment assigned to the wrong user
* Missing idempotency keys
* Refund authorization problems
* Incorrect rounding
* Floating-point arithmetic
* Editing posted financial records
* Deleting financial records
* Missing immutable audit trails
* Missing double-entry validation

Use database transactions and locking for financial operations.

## 12. Queue, scheduler, and job audit

Check:

* Jobs that may run more than once
* Missing uniqueness or idempotency
* Missing retry limits
* Infinite retries
* Unsafe serialized models
* Sensitive data in job payloads
* Failed-job handling
* Queue timeout settings
* Job timeout settings
* Backoff configuration
* Transaction timing
* Dispatching before commit
* Overlapping scheduled commands
* Scheduler locks
* Multi-server scheduler behavior
* Long-running jobs
* Memory leaks
* Error notification
* Queue-worker supervision
* Horizon configuration, if used

Confirm production uses a reliable process manager such as Supervisor or systemd.

## 13. Secrets and configuration audit

Inspect for:

* Committed `.env` files
* API keys in source code
* Database passwords
* Cloud credentials
* Private keys
* Payment secrets
* Debug logs containing secrets
* Secrets in frontend JavaScript
* Secrets in CI/CD logs
* Insecure environment variables
* Incorrect `APP_ENV`
* `APP_DEBUG=true`
* Weak `APP_KEY`
* Incorrect `APP_URL`
* Insecure mail configuration
* Unsafe trusted proxies
* Unsafe trusted hosts
* Incorrect session domain
* Incorrect cookie security settings
* Exposed Telescope, Horizon, Pulse, Debugbar, Ignition, phpMyAdmin, or Adminer

Do not print actual secret values in the report. Redact them.

## 14. Dependency and supply-chain audit

Review Composer, npm, Docker, GitHub Actions, and other dependencies.

Check:

* Known vulnerabilities
* Abandoned packages
* Unmaintained packages
* Packages from unknown sources
* Overly permissive version constraints
* Development packages installed in production
* Composer scripts
* npm lifecycle scripts
* Lock-file integrity
* Dependency confusion
* Typosquatting risks
* Unsafe GitHub Actions
* Actions not pinned to trusted versions or commit hashes
* Exposed CI/CD secrets
* Unnecessary packages

Run or recommend:

* `composer audit`
* `composer validate`
* `npm audit`
* Static analysis
* Secret scanning
* Dependency review

Do not automatically update major dependencies without explaining compatibility risk.

## 15. Error handling and logging audit

Check for:

* Stack traces exposed to users
* Sensitive values in logs
* Passwords, tokens, cookies, card data, or personal information in logs
* Silent failures
* Catching broad exceptions without handling them
* Returning exception messages directly
* Missing structured logs
* Missing correlation IDs
* Missing audit logs
* Log-forging risks
* Log retention
* Excessive logging
* Missing production error monitoring

Ensure users receive safe error messages while developers receive sufficient diagnostic information.

## 16. Privacy and sensitive-data audit

Identify all personal, financial, confidential, and regulated data.

Check:

* Data minimization
* Encryption at rest where required
* Encrypted backups
* TLS in transit
* Password and token protection
* Access logging
* Data export controls
* Data deletion
* Retention policies
* Sensitive data in URLs
* Sensitive data in logs
* Sensitive data in browser storage
* Excessive API responses
* Publicly accessible documents
* Admin access to personal data
* Backup access control

## 17. Production server and deployment audit

Review:

* Nginx or Apache configuration
* PHP-FPM configuration
* File and folder permissions
* Deployment user permissions
* SSH configuration
* Firewall
* Open ports
* TLS configuration
* Security headers
* Rate limiting
* Database exposure
* Redis exposure
* Queue workers
* Scheduler
* Process supervision
* Log rotation
* Disk-space monitoring
* Backup jobs
* Restore tests
* Health checks
* Maintenance mode
* Zero-downtime deployment
* Rollback procedure
* Correct Laravel caching commands

Verify production deployment includes:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

Confirm that caching commands are compatible with the project and that no closure-based routes prevent route caching.

## 18. Availability, backups, and disaster recovery

Check:

* Automated database backups
* Uploaded-file backups
* Off-site backup storage
* Backup encryption
* Backup retention
* Restore testing
* Point-in-time recovery
* Server monitoring
* Application monitoring
* Queue monitoring
* Disk monitoring
* Database monitoring
* Uptime checks
* Alerting
* Incident-response procedure
* Recovery time objective
* Recovery point objective

A backup is not considered reliable until restoration has been tested.

## 19. Code-quality and architecture audit

Review whether the implementation is professional and maintainable.

Check for:

* Fat controllers
* Business logic in Blade files
* Business logic in models
* Duplicate logic
* God classes
* Excessive service abstractions
* Tight coupling
* Missing interfaces where useful
* Incorrect repository pattern usage
* Inconsistent naming
* Mixed responsibilities
* Poor exception handling
* Weak domain boundaries
* Missing DTOs
* Missing enums
* Missing actions or services
* Missing policies
* Missing Form Requests
* Missing API Resources
* Hidden side effects
* Circular dependencies
* Difficult-to-test code
* Dead code
* Commented-out code
* Debug statements
* `dd()`, `dump()`, or `ray()` left in production code

Do not recommend design patterns merely for appearance. Recommend architectural changes only when they improve security, correctness, testability, or maintainability.

## 20. Testing audit

Inspect existing tests and identify missing tests.

Require coverage for:

* Authentication
* Authorization
* Ownership restrictions
* Role and permission boundaries
* Validation
* IDOR attempts
* Business state transitions
* Concurrent requests
* Duplicate submissions
* Financial calculations
* Stock calculations
* Payment webhooks
* File uploads
* Rate limiting
* Failed jobs
* API responses
* Tenant isolation
* Regression tests for every critical vulnerability found

Provide executable PHPUnit or Pest tests for critical findings.

## Tools and checks

Where available, use or recommend:

```bash
php artisan about
php artisan route:list
php artisan config:show
php artisan model:show
composer validate
composer audit
npm audit
phpstan analyse
./vendor/bin/pest
php artisan test
```

Also inspect the code manually. Tool output alone is not sufficient.

Do not run destructive commands, modify production data, expose secrets, send emails, trigger payments, delete records, or execute migrations without explicit approval.

## Severity levels

Classify every finding as:

* Critical: Immediate compromise, authentication bypass, remote code execution, payment manipulation, unrestricted sensitive-data access, or major data-loss risk.
* High: Significant privilege escalation, IDOR, stored XSS, insecure uploads, major authorization failure, serious business-logic abuse, or exposed secrets.
* Medium: Security weakness requiring specific conditions, missing hardening, limited data exposure, race condition, or reliability issue.
* Low: Minor hardening, code-quality issue, or limited operational risk.
* Informational: Improvement with no direct current vulnerability.

Also assign:

* Exploitability: Easy / Moderate / Difficult
* Impact: Low / Medium / High / Critical
* Confidence: Confirmed / High / Medium / Low
* Status: Vulnerable / Potentially vulnerable / Secure / Not verified

## Required output format

### A. Executive summary

Include:

* Overall security rating out of 10
* Production-readiness rating out of 10
* Architecture-quality rating out of 10
* Number of Critical, High, Medium, Low, and Informational findings
* Top five immediate risks
* Whether the website should remain publicly accessible
* Whether any issue requires immediate emergency action

### B. Architecture and attack-surface map

Describe:

* Entry points
* Trust boundaries
* Privileged roles
* Sensitive data
* External integrations
* High-risk workflows
* Publicly reachable services

### C. Detailed findings

For every finding, use this format:

#### Finding ID and title

* Severity:
* Category:
* CWE or OWASP mapping:
* Affected file:
* Affected method:
* Affected route or endpoint:
* Line numbers:
* User roles affected:
* Exploitability:
* Impact:
* Confidence:
* Status:

**Problem**

Explain exactly what is wrong.

**Evidence**

Show the relevant code or configuration. Redact secrets.

**Attack scenario**

Explain a realistic way the issue could be abused. Do not provide destructive instructions against systems outside this authorized project.

**Business impact**

Explain the effect on users, money, inventory, privacy, operations, or reputation.

**Recommended fix**

Provide a precise Laravel-compatible fix.

**Corrected code**

Provide production-ready replacement code or a patch.

**Required test**

Provide a PHPUnit or Pest regression test.

**Verification**

Explain how to confirm that the fix works.

### D. Route security matrix

Create a table containing:

* HTTP method
* Route
* Controller action
* Authentication middleware
* Authorization middleware
* Policy or permission
* Ownership or tenant check
* Rate limit
* CSRF requirement
* Risk
* Status

Review every route, not only obvious admin routes.

### E. Model security matrix

For each model, report:

* Fillable fields
* Guarded fields
* Hidden fields
* Casts
* Sensitive fields
* Ownership field
* Tenant or organization field
* Policy
* Soft-delete behavior
* Main risks

### F. Production configuration report

Report the status of:

* `APP_ENV`
* `APP_DEBUG`
* `APP_KEY`
* HTTPS
* Session security
* Cookie security
* CORS
* Trusted proxies
* Trusted hosts
* Logging
* Cache
* Redis
* Queue
* Scheduler
* Backups
* Mail
* Storage
* Database security
* Server permissions
* Firewall
* Monitoring

Never display secret values.

### G. Prioritized remediation plan

Divide fixes into:

1. Emergency: Fix immediately.
2. Within 24 hours.
3. Within 7 days.
4. Within 30 days.
5. Long-term improvements.

For each item include:

* Finding ID
* Required action
* Estimated complexity: Small / Medium / Large
* Regression risk
* Required testing
* Whether downtime is required

### H. Final production checklist

End with a checklist marked as:

* Passed
* Failed
* Partially passed
* Not verified

Do not mark anything as passed unless you have verified it from code, configuration, test results, or reliable evidence.

## Important audit rules

* Never assume that a method is safe because it uses Laravel.
* Never mark an item secure without evidence.
* Trace authorization through middleware, policies, controllers, services, jobs, and models.
* Verify server-side enforcement even when the frontend hides an action.
* Follow data from request input to database write and response output.
* Review both success and failure paths.
* Review asynchronous code and scheduled tasks.
* Check race conditions and duplicate requests.
* Prefer database constraints in addition to application validation.
* Use database transactions for multi-record operations.
* Use row locking for critical concurrent financial or inventory operations.
* Do not suggest disabling security controls to fix functionality.
* Do not expose actual passwords, keys, tokens, customer data, or production secrets.
* Clearly state anything that could not be verified.
* Separate confirmed vulnerabilities from theoretical risks.
* Avoid false positives by checking surrounding code and framework behavior.
* Provide fixes compatible with the project’s exact Laravel and PHP versions.

Begin by creating an inventory of the project structure, routes, roles, sensitive workflows, and external integrations. Then perform the audit systematically and produce the report in the required format.
