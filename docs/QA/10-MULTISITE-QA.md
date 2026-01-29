# Multisite QA Checklist

Complete multisite quality assurance checklist.

## Network Activation

### Activation Process
- [ ] Network activation works
- [ ] Tables created per site
- [ ] Options set per site

## Per-Site Provisioning

### Table Creation
- [ ] Tables created per site
- [ ] Proper prefixes (`wp_X_`)
- [ ] No conflicts

### Options
- [ ] Options set per site
- [ ] Network options where needed
- [ ] Proper isolation

## Settings Inheritance Rules

### Network Settings
- [ ] Network-wide settings available
- [ ] Per-site overrides allowed
- [ ] Proper inheritance

## Cron Events Per Site

### Site-Specific Cron
- [ ] Cron events per site
- [ ] Proper scheduling
- [ ] No cross-site execution

## DB Table Creation Per Site

### Table Creation
- [ ] Automatic on site creation
- [ ] Proper prefixes
- [ ] No manual intervention

## Plugin Deactivation/Uninstall in Multisite

### Deactivation
- [ ] Per-site deactivation
- [ ] Network deactivation
- [ ] Proper cleanup

### Uninstall
- [ ] Per-site uninstall
- [ ] Network uninstall
- [ ] Data removal option

## Cross-Site Contamination Prevention

### Data Isolation
- [ ] No cross-site data access
- [ ] Proper table prefixes
- [ ] Option isolation














