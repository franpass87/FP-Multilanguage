# Database & Data Integrity QA Checklist

Complete database and data integrity quality assurance checklist.

## Schema Validation

### Table Creation
- [ ] Tables created on activation
- [ ] Proper schema versioning
- [ ] Migration system works

### Table Structure
- [ ] Correct columns
- [ ] Proper indexes
- [ ] Foreign keys where needed

### Tables to Validate
- [ ] `wp_fpml_queue`
- [ ] `wp_fpml_translation_versions`
- [ ] `wp_fpml_logs`

## Installation/Migration Behavior

### Initial Installation
- [ ] Tables created
- [ ] Default options set
- [ ] No errors

### Version Upgrades
- [ ] Migrations run
- [ ] Data preserved
- [ ] Schema updated

## Data Retention Rules

### Queue Jobs
- [ ] Old completed jobs cleaned up
- [ ] Failed jobs retained for review
- [ ] Active jobs never deleted

### Translation Versions
- [ ] Version history retained
- [ ] Old versions cleaned up
- [ ] Configurable retention

### Logs
- [ ] Log retention configurable
- [ ] Old logs cleaned up
- [ ] Important logs retained

## Cleanup Routines

### Scheduled Cleanup
- [ ] Cron jobs for cleanup
- [ ] Proper scheduling
- [ ] No data loss

### Manual Cleanup
- [ ] CLI commands for cleanup
- [ ] Admin UI for cleanup
- [ ] Safe cleanup operations

## Export/Import Logic

### Data Export
- [ ] Export translations
- [ ] Export settings
- [ ] Export queue state

### Data Import
- [ ] Import translations
- [ ] Import settings
- [ ] Validate imported data

## Deletion/Rollback Behavior

### Translation Deletion
- [ ] Source deletion cleans up target
- [ ] Target deletion preserves source
- [ ] Relationship cleanup

### Rollback Capability
- [ ] Version rollback works
- [ ] Data restoration
- [ ] Relationship restoration

## Multisite Table Behavior

### Per-Site Tables
- [ ] Tables created per site
- [ ] Proper table prefixes
- [ ] No cross-site contamination

### Network Tables
- [ ] Network-wide tables where needed
- [ ] Proper isolation
- [ ] Shared data where appropriate

## Orphan Prevention

### Translation Pairs
- [ ] Source deletion cleans target
- [ ] Target deletion preserves source
- [ ] Pair relationships maintained

### Metadata
- [ ] Meta cleaned up on deletion
- [ ] No orphaned meta
- [ ] Relationship integrity














