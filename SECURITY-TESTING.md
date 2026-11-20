# Security testing

The `Security Scans` workflow runs on pull requests, pushes to `main`, weekly, and by manual dispatch. It audits Composer and npm dependencies, runs PHP-focused Semgrep rules, and runs an OWASP ZAP passive baseline scan against a local non-debug instance. ZAP reports are retained as workflow artifacts for 14 days.

The ZAP baseline scan crawls reachable unauthenticated pages and evaluates passive findings. It does not perform attack payloads, test authenticated routes, or replace a penetration test. Semgrep and dependency audits also produce findings that require human review; a clean run is not proof that the application is secure.

For release readiness, review scan findings and application changes against the current OWASP Application Security Verification Standard (ASVS), exercise authorization and tenant isolation, inspect queue and SMTP secret handling, and arrange an independent penetration test before a production service handles real contact data.

LaraScan is an optional supplementary Laravel reconnaissance tool. Its checks cover exposed framework details, debug mode, development tools, and host-header behavior. Treat it as a narrow extra check rather than a substitute for code analysis, dependency audits, DAST, or penetration testing.
