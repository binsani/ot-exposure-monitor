# Security posture and release checklist

## Implemented controls

- Organization-owned models have query-level tenant scoping; cross-tenant route binding is tested.
- State-changing web requests require authenticated membership and role checks.
- Agent API tokens are random, shown once, stored only as SHA-256 hashes, revocable, rate-limited, and bound to one organization.
- CSRF protection covers browser actions; agent endpoints use bearer authentication and accept JSON only.
- Passwords use Laravel's adaptive hash; login attempts are rate-limited.
- Security headers disable framing and MIME sniffing, restrict browser capabilities, and enable HSTS on HTTPS requests.
- Consequential acknowledgments, baseline changes, playbook revisions, authorizations, drills, incidents, agent enrollment/revocation, and job retries write audit records.
- CI runs backend/frontend tests, production asset compilation, dependency audits, and automated dependency update checks.
- Public registration can be disabled in production.

## Pre-release checks

- Confirm `APP_DEBUG=false`, HTTPS-only traffic, secure encrypted cookies, and public registration disabled.
- Verify the database user cannot create databases or access other databases.
- Verify Redis is private and authenticated; never expose it publicly.
- Confirm mail, queue, scheduler, failed-job, backup, and restore tests.
- Review active organization memberships and revoke departed users and agents.
- Run `composer audit --locked --no-dev`, `npm audit --omit=dev`, the full test suite, and a production build.
- Validate Shodan quota/error behavior and CISA ingestion with production network egress.
- Repeat tenant-isolation and role acceptance tests after material authorization changes.

## Deliberate limitations

OTEIM does not intercept or validate proprietary PLC authentication protocols. Authentication status is a symptom reported by an approved collector, not proof of a credential change. The generic agent avoids storing device credentials. Shodan data is observational and may lag actual exposure. SMS and webhook delivery are future channels; v1 uses Laravel notifications and email.

Report suspected vulnerabilities privately to the repository owner. Do not include credentials, live utility addresses, or sensitive device data in an issue.
