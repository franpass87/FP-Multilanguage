# REST API QA Checklist

Complete REST API quality assurance checklist.

## Endpoint Registration

### Route Registration
- [ ] All routes registered on `rest_api_init`
- [ ] Proper namespaces (`fpml/v1`)
- [ ] No route conflicts

## Authentication

### Authentication Methods
- [ ] API key authentication
- [ ] Nonce authentication
- [ ] User capability checks

### Tested Scenarios
- [ ] Without authentication → 401
- [ ] With invalid auth → 401/403
- [ ] With valid auth → 200

## Permission Checks

### Required Capabilities
- [ ] `manage_options` for admin endpoints
- [ ] `edit_posts` for content endpoints
- [ ] Proper checks on all endpoints

### Tested With
- [ ] Admin user
- [ ] Editor user
- [ ] Subscriber user

## Input Validation

### Required Fields
- [ ] Required fields enforced
- [ ] Clear error messages
- [ ] Proper validation rules

### Type Validation
- [ ] String, integer, boolean, array
- [ ] Proper type checking
- [ ] Type conversion where appropriate

## Output Sanitization

### Data Sanitization
- [ ] All output data sanitized
- [ ] Proper sanitization functions
- [ ] No sensitive data exposure

## HTTP Status Codes

### Success Codes
- [ ] 200 OK for successful GET/PUT
- [ ] 201 Created for successful POST
- [ ] Proper use of status codes

### Error Codes
- [ ] 400 Bad Request for invalid input
- [ ] 401 Unauthorized for auth failures
- [ ] 403 Forbidden for permission failures
- [ ] 404 Not Found for missing resources
- [ ] 500 Internal Server Error for server errors

## Error Structures

### Error Response Format
```json
{
  "code": "error_code",
  "message": "Error message",
  "data": {
    "status": 400
  }
}
```

- [ ] Consistent error format
- [ ] Meaningful error codes
- [ ] Clear error messages

## Performance Under Load

### Response Times
- [ ] Endpoints respond within 2 seconds
- [ ] No timeouts under normal load
- [ ] Proper caching where applicable

### Concurrent Requests
- [ ] Handle multiple requests
- [ ] No race conditions
- [ ] Proper locking mechanisms

## Rate Limiting/Caching

### Rate Limiting
- [ ] Implemented where needed
- [ ] Proper limits set
- [ ] Clear error messages on limit

### Caching
- [ ] Cache appropriate responses
- [ ] Proper cache headers
- [ ] Cache invalidation














