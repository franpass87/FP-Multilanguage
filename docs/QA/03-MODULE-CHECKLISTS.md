# Module-by-Module QA Checklists

Detailed checklists for each module with preconditions, test scenarios, steps, expected results, edge cases, and testing methods.

## Kernel Module

### Preconditions
- [ ] PHP 8.0+ installed
- [ ] Composer autoloader present
- [ ] WordPress loaded

### Test Scenarios
1. [ ] New bootstrap initialization (feature flag off)
2. [ ] Legacy bootstrap fallback (feature flag on)
3. [ ] Service provider registration
4. [ ] Container resolution
5. [ ] Error handling (missing autoloader, service errors)

### Steps
1. [ ] Activate plugin with new bootstrap
2. [ ] Verify Kernel\Plugin instance created
3. [ ] Verify all service providers registered
4. [ ] Test container get() for each service
5. [ ] Simulate autoloader missing, verify error notice
6. [ ] Simulate service provider error, verify fallback

### Expected Results
- [ ] Plugin activates without fatal errors
- [ ] All services resolvable from container
- [ ] Graceful fallback on errors
- [ ] Admin notice on critical failures

### Edge Cases
- [ ] Multiple activation attempts
- [ ] Deactivation during initialization
- [ ] Missing vendor directory

### Testing Method
- **Automated**: PHPUnit integration tests
- **Manual**: Activation/deactivation cycles

## Foundation Module

### Preconditions
- [ ] WordPress options API available
- [ ] Transients API available

### Test Scenarios
1. [ ] Logger: All log levels (debug, info, warning, error)
2. [ ] Cache: Set, get, delete, expiration
3. [ ] Options: Get, set, delete, autoload management
4. [ ] HTTP Client: GET, POST, timeout, error handling
5. [ ] Validator: Required, type, format validation
6. [ ] Sanitizer: Text, email, URL, integer sanitization

### Steps
1. [ ] Test logger with different levels
2. [ ] Test cache set/get with expiration
3. [ ] Test options autoload migration
4. [ ] Test HTTP client with mock responses
5. [ ] Test validator with valid/invalid inputs
6. [ ] Test sanitizer with various inputs

### Expected Results
- [ ] All log levels write to database
- [ ] Cache respects expiration
- [ ] Options properly autoloaded
- [ ] HTTP client handles errors gracefully
- [ ] Validator rejects invalid inputs
- [ ] Sanitizer cleans all inputs

### Testing Method
- **Automated**: PHPUnit unit tests
- **Manual**: Settings page operations

## Domain Module

### Preconditions
- [ ] WordPress database accessible
- [ ] Posts/terms exist

### Test Scenarios
1. [ ] PostTranslationService: Create, update, delete translations
2. [ ] TermTranslationService: Create, update, delete translations
3. [ ] TranslationRepository: Query by source, target, status
4. [ ] Model validation: Invalid IDs, missing relationships

### Steps
1. [ ] Create IT post, translate to EN
2. [ ] Update IT post, verify EN sync
3. [ ] Delete IT post, verify EN cleanup
4. [ ] Query translations by various criteria
5. [ ] Test with invalid post IDs
6. [ ] Test with missing pair relationships

### Expected Results
- [ ] Translations created with correct metadata
- [ ] Updates synchronized properly
- [ ] Deletions clean up relationships
- [ ] Queries return correct results
- [ ] Invalid inputs throw exceptions

### Testing Method
- **Automated**: PHPUnit integration tests with test database
- **Manual**: Post translation workflows

## Admin Module

### Preconditions
- [ ] User with `manage_options` capability
- [ ] Plugin activated

### Test Scenarios
1. [ ] Dashboard: Statistics display, real-time updates
2. [ ] Settings: Save/load, validation, sanitization
3. [ ] Bulk Translator: Select posts, queue jobs, progress
4. [ ] Translation History: Filter, search, pagination
5. [ ] Translation Metabox: Status, actions, AJAX

### Steps
1. [ ] Access dashboard, verify statistics
2. [ ] Change settings, save, reload page
3. [ ] Select multiple posts, bulk translate
4. [ ] View translation history, filter by date/status
5. [ ] Edit post, check metabox, trigger translation

### Expected Results
- [ ] Dashboard shows accurate statistics
- [ ] Settings persist correctly
- [ ] Bulk operations queue jobs
- [ ] History displays correctly
- [ ] Metabox shows current status

### Testing Method
- **Automated**: PHPUnit for AJAX handlers
- **Manual**: Full admin UI workflows

## Frontend Routing Module

### Preconditions
- [ ] Rewrite rules flushed
- [ ] Routing mode set to 'segment'

### Test Scenarios
1. [ ] IT URL: `/post-slug/` → No rewrite
2. [ ] EN URL: `/en/post-slug/` → Rewritten to IT post
3. [ ] Language detection: URL, cookie, browser
4. [ ] Rewrite flush: On activation, settings change

### Steps
1. [ ] Visit IT post URL, verify no rewrite
2. [ ] Visit EN post URL, verify rewrite
3. [ ] Switch language via widget, verify URL change
4. [ ] Activate plugin, verify rewrite flush
5. [ ] Change routing mode, verify rewrite update

### Expected Results
- [ ] IT URLs work normally
- [ ] EN URLs rewrite correctly
- [ ] Language detection works
- [ ] Rewrite rules flush when needed

### Testing Method
- **Automated**: PHPUnit for rewrite registration
- **Manual**: Browser testing with various URLs

## REST API Module

### Preconditions
- [ ] REST API enabled
- [ ] User authenticated

### Test Scenarios
1. [ ] Authentication: API key, nonce, capability checks
2. [ ] Endpoints: GET (list), POST (create), PUT (update), DELETE (delete)
3. [ ] Validation: Required fields, type validation
4. [ ] Error handling: 400, 401, 403, 500 responses

### Steps
1. [ ] Test without authentication, verify 401
2. [ ] Test with invalid nonce, verify 403
3. [ ] Test with valid auth, verify 200
4. [ ] Test invalid parameters, verify 400
5. [ ] Test server error, verify 500

### Expected Results
- [ ] All endpoints require authentication
- [ ] Valid requests return 200 with JSON
- [ ] Invalid requests return appropriate errors
- [ ] Error responses include message/details

### Testing Method
- **Automated**: PHPUnit REST API tests
- **Manual**: Postman/curl testing

## CLI Module

### Preconditions
- [ ] WP-CLI installed
- [ ] Plugin activated

### Test Scenarios
1. [ ] Command syntax: Arguments, options, help
2. [ ] Queue commands: Process, status, clear
3. [ ] Utility commands: Sync, migrate, cleanup
4. [ ] Error handling: Invalid args, permission errors

### Steps
1. [ ] Run `wp fpml --help`, verify help text
2. [ ] Run queue commands, verify output
3. [ ] Run utility commands, verify results
4. [ ] Test invalid arguments, verify errors

### Expected Results
- [ ] Help text displays correctly
- [ ] Commands execute successfully
- [ ] Output formatted properly
- [ ] Errors displayed clearly

### Testing Method
- **Automated**: PHPUnit CLI tests
- **Manual**: WP-CLI command execution

## Queue Module

### Preconditions
- [ ] Queue table exists
- [ ] Cron enabled

### Test Scenarios
1. [ ] Enqueue: Add job, verify status
2. [ ] Process: Execute job, update status
3. [ ] Retry: Failed jobs, retry logic
4. [ ] Cleanup: Old jobs, completed jobs

### Steps
1. [ ] Enqueue translation job
2. [ ] Verify job in queue table
3. [ ] Trigger queue processing
4. [ ] Verify job status updated
5. [ ] Test failed job retry
6. [ ] Test cleanup of old jobs

### Expected Results
- [ ] Jobs enqueued correctly
- [ ] Processing updates status
- [ ] Failed jobs retried
- [ ] Old jobs cleaned up

### Testing Method
- **Automated**: PHPUnit queue tests
- **Manual**: Monitor queue processing

## Translation Module

### Preconditions
- [ ] Translation provider configured
- [ ] Source content exists

### Test Scenarios
1. [ ] Post translation: Create EN post from IT
2. [ ] Term translation: Create EN term from IT
3. [ ] Meta translation: Translate post meta
4. [ ] Relationship mapping: Preserve relationships
5. [ ] Provider fallback: Primary fails, use fallback

### Steps
1. [ ] Create IT post with content
2. [ ] Trigger translation
3. [ ] Verify EN post created
4. [ ] Verify meta translated
5. [ ] Verify relationships mapped
6. [ ] Test provider error, verify fallback

### Expected Results
- [ ] Translations created correctly
- [ ] Content translated accurately
- [ ] Meta synchronized
- [ ] Relationships preserved
- [ ] Fallback works on errors

### Testing Method
- **Automated**: PHPUnit with mock providers
- **Manual**: Real translation workflows

## Integration Modules

### WooCommerce Support
- [ ] **Preconditions**: WooCommerce active
- [ ] **Test Scenarios**: Product translation, variation sync, attribute translation, gallery sync
- [ ] **Steps**: Create IT product, translate, verify EN product, check variations, attributes, gallery
- [ ] **Expected Results**: All product data translated and synchronized

### Salient Theme Support
- [ ] **Preconditions**: Salient theme active
- [ ] **Test Scenarios**: 70+ meta fields translation
- [ ] **Steps**: Create post with Salient meta, translate, verify EN meta
- [ ] **Expected Results**: All meta fields translated

### FP-SEO Support
- [ ] **Preconditions**: FP-SEO-Manager active
- [ ] **Test Scenarios**: 25+ SEO meta fields translation
- [ ] **Steps**: Create post with SEO meta, translate, verify EN meta
- [ ] **Expected Results**: All SEO meta translated














