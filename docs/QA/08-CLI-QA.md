# WP-CLI QA Checklist

Complete WP-CLI quality assurance checklist.

## Command Syntax

### Command Structure
- [ ] `wp fpml <command> [<args>] [--<options>]`
- [ ] Proper command hierarchy
- [ ] Clear command names

### Help Text
- [ ] `wp fpml --help` displays help
- [ ] `wp fpml <command> --help` displays command help
- [ ] Clear descriptions

## Argument Validation

### Required Arguments
- [ ] Required args enforced
- [ ] Clear error messages
- [ ] Help text shows requirements

### Optional Arguments
- [ ] Default values work
- [ ] Optional args processed correctly

## Error Handling

### Error Messages
- [ ] Clear error messages
- [ ] Proper exit codes
- [ ] Error logging

### Exit Codes
- [ ] 0 for success
- [ ] 1 for general error
- [ ] Proper codes for specific errors

## Output Formatting

### Success Output
- [ ] Clear success messages
- [ ] Proper formatting
- [ ] Useful information

### Progress Output
- [ ] Progress indicators
- [ ] Status updates
- [ ] Clear formatting

## Permission Checks

### Required Permissions
- [ ] Proper capability checks
- [ ] Clear error messages
- [ ] Graceful failure

## Bulk Operations Safety

### Large Datasets
- [ ] Handle large datasets
- [ ] Progress indicators
- [ ] Memory management

### Safety Checks
- [ ] Confirmation prompts
- [ ] Dry-run options
- [ ] Rollback capabilities

## Long-Running Task Handling

### Progress Updates
- [ ] Regular progress updates
- [ ] Time estimates
- [ ] Status indicators

### Interruption Handling
- [ ] Graceful interruption
- [ ] State preservation
- [ ] Resume capability














