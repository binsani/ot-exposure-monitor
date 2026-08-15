# OTEIM Local Agent

The local agent is a dependency-free PHP collector for utility-owned Windows or Linux hosts. It downloads only the tenant's assets configured with the `agent` monitoring source, performs conservative TCP reachability/banner checks, optionally hashes a locally exported configuration file, and sends integrity readings to the dashboard.

## Requirements

- PHP 8.3+ with cURL and OpenSSL
- Outbound HTTPS access to the OTEIM dashboard
- Network access to only the OT devices explicitly inventoried for agent monitoring

## Install

1. Enroll an agent from **Agents** in the dashboard and copy the token immediately.
2. Copy this directory to the collector host.
3. Copy `config.example.json` to `config.json`, then set the HTTPS dashboard URL and token.
4. Run `php oteim-agent.php config.json` interactively once.
5. Schedule that command every five minutes with Windows Task Scheduler or cron.

The token is equivalent to a password. Restrict the configuration file to the service account, never email it, and revoke it from the dashboard if the collector is retired or compromised. The server stores only a SHA-256 hash of the token.

Device-specific authentication is intentionally not built into the generic agent. Add optional readings through `asset_overrides`; do not place PLC passwords in this configuration. Internet exposure continues to be verified by the cloud-side Shodan check because an internal collector cannot reliably prove what an outside host can reach.
