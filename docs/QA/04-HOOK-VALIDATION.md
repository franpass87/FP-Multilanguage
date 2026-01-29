# Hook & WordPress Lifecycle Validation

Complete validation of all WordPress hooks registered by FP Multilanguage plugin.

## Critical Hooks

### plugins_loaded (priority 1)
- **Handler**: `fpml_bootstrap()`
- **Purpose**: Register services, initialize integrations
- **Must Happen**: All services registered, integrations loaded
- **Must NOT Happen**: Fatal errors, missing dependencies
- **Failure Conditions**: Missing autoloader, service errors
- **Validation**: [ ] Registered at priority 1, [ ] No fatal errors, [ ] All services available

### plugins_loaded (priority 5)
- **Handler**: `fpml_do_activation()`
- **Purpose**: Process deferred activation
- **Must Happen**: Database tables created, options initialized
- **Must NOT Happen**: Duplicate activation, data loss
- **Failure Conditions**: Database errors, permission issues
- **Validation**: [ ] Registered at priority 5, [ ] Tables created, [ ] Options initialized

### plugins_loaded (priority 10)
- **Handler**: `fpml_run_plugin()` or `Bootstrap::boot()`
- **Purpose**: Initialize plugin instance
- **Must Happen**: Plugin fully initialized
- **Must NOT Happen**: Double initialization
- **Failure Conditions**: Missing Plugin class, initialization errors
- **Validation**: [ ] Registered at priority 10, [ ] Plugin initialized, [ ] No double init

### init (priority 999)
- **Handler**: `fpml_maybe_flush_rewrites()`
- **Purpose**: Flush rewrite rules if needed
- **Must Happen**: Rewrite rules updated when needed
- **Must NOT Happen**: Unnecessary flushes, performance impact
- **Failure Conditions**: Rewrite registration errors
- **Validation**: [ ] Registered at priority 999, [ ] Conditional flush, [ ] No unnecessary flushes

## Admin Hooks

### admin_menu
- **Handler**: `Admin::register_admin_menu()`
- **Purpose**: Register admin menu pages
- **Must Happen**: Menu pages registered with correct capabilities
- **Must NOT Happen**: Duplicate menus, permission bypass
- **Failure Conditions**: Capability errors, menu conflicts
- **Validation**: [ ] Menu registered, [ ] Capabilities checked, [ ] No duplicates

### admin_enqueue_scripts
- **Handler**: `Admin::enqueue_admin_assets()`
- **Purpose**: Load admin CSS/JS
- **Must Happen**: Assets loaded on plugin pages only
- **Must NOT Happen**: Asset conflicts, missing files
- **Failure Conditions**: File not found, dependency errors
- **Validation**: [ ] Assets loaded conditionally, [ ] Dependencies correct, [ ] No conflicts

### add_meta_boxes
- **Handler**: `TranslationMetabox::register_metabox()`
- **Purpose**: Register translation metabox
- **Must Happen**: Metabox on supported post types
- **Must NOT Happen**: On unsupported types, duplicate metaboxes
- **Failure Conditions**: Post type not registered
- **Validation**: [ ] Metabox on correct types, [ ] No duplicates, [ ] Proper context

## Frontend Hooks

### template_redirect
- **Handler**: `Rewrites::handle_rewrite()`
- **Purpose**: Handle /en/ URL rewriting
- **Must Happen**: EN URLs rewrite to IT posts
- **Must NOT Happen**: IT URLs rewritten, infinite loops
- **Failure Conditions**: Rewrite rule errors, missing posts
- **Validation**: [ ] EN URLs rewrite correctly, [ ] No loops, [ ] IT URLs unchanged

### wp_nav_menu_items
- **Handler**: `MenuFilter::filter_menu_items()`
- **Purpose**: Translate menu items
- **Must Happen**: Menu items translated on EN pages
- **Must NOT Happen**: IT menu items translated, duplicates
- **Failure Conditions**: Missing translations, filter conflicts
- **Validation**: [ ] Translation on EN pages only, [ ] No duplicates, [ ] Fallback works

## AJAX Hooks

### wp_ajax_fpml_* (all AJAX actions)
- **Handlers**: `AjaxHandlers::handle_*()`
- **Purpose**: Handle AJAX requests
- **Must Happen**: Nonce verification, capability checks, proper responses
- **Must NOT Happen**: Unauthorized access, XSS, CSRF
- **Failure Conditions**: Missing nonce, invalid permissions, errors
- **Validation**: [ ] Nonce verified, [ ] Capabilities checked, [ ] Output escaped

## Cron Hooks

### fpml_process_queue
- **Handler**: `Queue::process_queue()`
- **Purpose**: Process translation queue
- **Must Happen**: Jobs processed, status updated
- **Must NOT Happen**: Duplicate processing, data loss
- **Failure Conditions**: Queue lock, processing errors
- **Validation**: [ ] Jobs processed, [ ] Status updated, [ ] No duplicates

### fpml_cleanup_queue
- **Handler**: `Queue::cleanup_old_jobs()`
- **Purpose**: Clean up old queue jobs
- **Must Happen**: Old jobs deleted
- **Must NOT Happen**: Active jobs deleted
- **Failure Conditions**: Cleanup errors, data loss
- **Validation**: [ ] Old jobs cleaned, [ ] Active jobs preserved, [ ] No data loss

## REST API Hooks

### rest_api_init
- **Handler**: `RestAdmin::register_routes()`
- **Purpose**: Register REST endpoints
- **Must Happen**: All endpoints registered with proper permissions
- **Must NOT Happen**: Duplicate routes, permission bypass
- **Failure Conditions**: Route conflicts, permission errors
- **Validation**: [ ] Routes registered, [ ] Permissions set, [ ] No conflicts

## Hook Validation Checklist

- [ ] All hooks have unique priorities
- [ ] No duplicate hook registrations
- [ ] All hooks have remove_action/remove_filter support
- [ ] No expensive operations in loops
- [ ] Admin hooks only in admin context
- [ ] Frontend hooks only on frontend
- [ ] AJAX hooks properly namespaced
- [ ] Cron hooks properly scheduled
- [ ] REST hooks properly authenticated

## Validation Script

Run the hook validator:
```bash
php tools/qa-hook-validator.php
```

This will:
1. Scan all plugin files for hook registrations
2. Check for duplicates
3. Validate priorities
4. Check lifecycle correctness
5. Identify dangerous hooks
6. Verify context-specific conditions
7. Generate a comprehensive report














