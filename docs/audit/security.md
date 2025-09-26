# Security Audit (Phase 4)

## Summary
- Enforced nonce validation across manual string and translation REST endpoints using a shared validator that recognises both plugin-specific and `wp_rest` actions.
- Guarded manual string updates with stricter key/language normalisation before persisting values through the repository sanitizers.
- Required capability and nonce checks on settings reads to keep REST exposure aligned with admin expectations.

## Validation Notes
- REST nonce validation bypasses cookie checks only when alternative authentication headers are present, preserving support for application passwords and CLI automation.
- Manual string payloads continue to pass through repository sanitization (`wp_kses_post`) ensuring HTML restrictions remain consistent between REST and AJAX flows.

## Follow-up
- Re-run runtime logging once security work is merged to confirm no new warnings are emitted from tightened REST permission callbacks.
- Document REST authentication requirements for integrators consuming the translation endpoints.
