# Operator and administrator runbook

## Daily checks

Review **Dashboard**, then **Alerts**. Critical exposure or identity changes should be acknowledged only after an owner has started investigation. Resolve an alert with a concrete outcome note. Open an incident when multiple alerts or operational actions need one durable record.

Administrators should check **Operations** for failed jobs. Correct the underlying network, quota, key, or mail issue before retrying. Repeated failures should remain visible while investigated.

## Backups

Take encrypted PostgreSQL backups at least daily and before every release. Retain daily copies for 35 days and monthly copies for 12 months unless policy requires longer. Back up the application `.env` separately in a secrets vault; do not put it in the database archive. The application storage directory contains temporary imports and logs, not the primary system of record.

Test restoration quarterly into an isolated environment:

1. Provision an empty PostgreSQL database.
2. Restore the selected archive.
3. deploy the exact application release associated with that backup.
4. Run migrations only after confirming the restored schema version.
5. Verify tenant counts, assets, alerts, playbook versions, incidents, and audit logs.
6. Destroy the isolated restore after documenting the test.

## Key and token rotation

Rotate Shodan, database, Redis, and mail credentials through the hosting secret store. Revoke and re-enroll a local agent if its configuration host is lost or compromised. Agent tokens are shown once and cannot be recovered from OTEIM.

## Local agents

Install the collector using `agent/README.md`. Use a dedicated low-privilege OS account, protect `config.json`, allow outbound HTTPS only to OTEIM, and allow OT access only to inventoried devices/ports. Review the last-report time on **Agents** after network or firewall changes.

## Incident reports

Use **Print report** from the incident record and choose the browser's Save as PDF function. This intentionally avoids server-side PDF rendering dependencies while producing a portable management, regulator, or insurer record.
