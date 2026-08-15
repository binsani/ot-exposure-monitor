# OT Exposure & Integrity Monitor — Full Build Prompt (3 Parts)

**How to use this file:** Paste PART 1, then PART 2, then PART 3 into your code editor / AI coding assistant as three separate messages, in order. Each part contains an explicit instruction not to begin coding until all three have been received. This is intentional — the parts depend on each other for full context, and starting early leads to inconsistent architecture decisions.

GitHub: `github.com/binsani` (repo to be created as `ot-exposure-monitor`, private, initialized with this spec as `/docs/PRODUCT_SPEC.md`)

---

## PART 1 of 3 — Vision, Architecture, Stack, and Data Model

```
You are acting as a senior full-stack engineer and technical architect. I am
giving you a complete product specification for a real, commercial software
product in three sequential messages. This is PART 1 OF 3.

### DO NOT WRITE ANY CODE, CREATE ANY FILES, SCAFFOLD ANY PROJECT, OR RUN ANY
### COMMANDS UNTIL I HAVE PASTED ALL 3 PARTS AND EXPLICITLY TELL YOU TO BEGIN.

Your only job after this message is to read, internally digest, and briefly
summarize back to me your understanding of what's being described — then wait.
Do not ask clarifying questions yet unless something is truly ambiguous; more
detail is coming in Parts 2 and 3 that will likely answer it. If you have
architecture concerns, note them briefly and continue waiting.

---

## 1. Product Name & One-Line Pitch

**OT Exposure & Integrity Monitor** ("OTEIM" internally, product-facing name TBD)

"Know the moment your PLC is reachable from the internet or its configuration
changes without authorization — before an attacker locks you out."

## 2. Why This Product Exists (context, not filler — use this to inform every
   design decision downstream)

Starting late July 2026, the FBI, CISA, and EPA issued public warnings after
coordinated attacks hit water and wastewater utilities across at least a
dozen US states, with 30+ confirmed incidents in Minnesota alone. The attack
pattern was consistent and, critically, unsophisticated:

- Attackers found internet-exposed Rockwell Automation/Allen-Bradley
  MicroLogix 1100 and 1400 series PLCs, reachable directly from the public
  internet with no network segmentation.
- They logged in (weak/default/reused credentials) and changed the operator
  password, locking legitimate staff out.
- They reassigned the PLC's IP address, breaking the SCADA system's
  connection to the device entirely.
- Utilities had no automated way to detect any of this. Operators discovered
  the compromise only when pumps stopped responding or HMI screens went
  blank — i.e., discovery happened operationally, not through monitoring.
- Recovery meant reverting to fully manual operation (physically operating
  valves/pumps on site) until the PLC could be recovered or replaced.
- Root cause in every public case: no inventory of what was internet-facing,
  no baseline of "normal" device state, no alerting on credential or IP
  changes, no compensating controls documented for unpatchable legacy gear.

This product exists to close exactly that gap for water utilities, and later
other OT-heavy sectors (power distribution, food & beverage, oil & gas
midstream) once the water-sector version is proven. Every feature in Parts 2
and 3 maps directly to one of the five root-cause gaps listed above. Keep
that traceability in mind — if a feature doesn't map to a real gap, question
whether it belongs in v1.

## 3. Target Buyer & Business Model (informs UX and architecture priorities)

- **Primary buyer, v1:** Small-to-midsize municipal water/wastewater
  utilities (the segment CISA explicitly flagged as most vulnerable and
  least resourced). These buyers have small IT/OT teams, tight budgets, and
  need something that works with minimal configuration — not an enterprise
  SIEM they need a specialist to run.
- **Secondary buyer, v2+:** Multi-site utility operators and industrial
  operators in adjacent sectors who need the same capability across many
  facilities from one dashboard, plus compliance/audit reporting features.
- **Model:** SaaS subscription, priced per monitored site or per monitored
  asset count. A free/trial tier scanning a limited number of assets is
  important for water-utility budget realities — design multi-tenancy in
  from day one even though v1 may only have a handful of real customers.
- **Deployment reality:** Some utilities will refuse to let scanning traffic
  originate from a third-party cloud IP touching their network, even
  passively. Architecture MUST support a lightweight on-prem/local agent
  option that reports up to the cloud dashboard, in addition to a pure
  cloud-scanning mode for customers who are fine with that. Design the data
  model so "how a given asset's data arrives" (agent-reported vs.
  cloud-scanned vs. manually entered) is a first-class distinction, not an
  afterthought.

## 4. Tech Stack (confirmed, do not deviate without discussion)

- **Backend:** Laravel (latest stable), PHP 8.3+
- **Frontend:** Vue 3 (Composition API), Inertia.js preferred over a
  separate SPA/API split unless you have a strong reason to argue otherwise
  — flag it if so, in your summary, and I'll decide before Part 2.
- **Database:** PostgreSQL (not MySQL — we want strong JSONB support for
  flexible device-property storage and better handling of time-series-ish
  integrity-check data)
- **Queue/Jobs:** Laravel queues (Redis-backed) for scheduled scans,
  advisory-feed polling, and alert dispatch — this is a scan/polling-heavy
  product, background jobs are core, not incidental
- **Auth:** Laravel Breeze/Fortify or Jetstream, with multi-tenancy
  (team/organization scoping) built in from the first migration
- **Styling:** Tailwind CSS
- **Testing:** Pest for backend, Vitest for frontend — this is a security
  product; test coverage on alerting logic and CVE-matching logic is not
  optional
- **Hosting target:** assume a standard VPS or Laravel Forge/Vapor-style
  deployment; nothing exotic

## 5. High-Level Architecture

Four logical modules (each detailed fully in Parts 2 and 3):

1. **Exposure Scanning** — is this asset reachable from the internet right
   now, and has that changed?
2. **Asset Inventory & Vulnerability Matching** — what do we own, and which
   known CVEs/advisories apply to it?
3. **Change & Integrity Alerting** — did something change on this device
   (credentials, IP, firmware, config) that wasn't authorized?
4. **Incident Playbook & Manual Fallback Tracker** — when something does go
   wrong, who's authorized to do what, and is there a paper trail?

These four modules share one core data model, described below. Do not treat
them as separate apps — they're views and workflows over the same asset
graph.

## 6. Core Data Model (draft — refine when we get to migrations, but this is
   the shape to design around)

- **organizations** — tenant boundary. id, name, sector (water/wastewater/
  power/other), plan_tier, created_at
- **users** — standard, belongs to organization, role (admin/operator/
  viewer/auditor)
- **sites** — a physical facility. belongs to organization. id, name,
  address, lat/lng (nullable), criticality_tier
- **assets** — the core entity. belongs to site. fields: id, name,
  asset_type (plc/rtu/hmi/historian/switch/other), vendor, model,
  firmware_version, internal_ip, external_ip (nullable), is_internet_facing
  (bool, derived + manually overridable), data_source_type (agent/
  cloud_scan/manual), last_seen_at, criticality (informs alert priority),
  raw_metadata (jsonb, for anything vendor-specific we haven't modeled yet)
- **asset_advisory_matches** — join between assets and known CVEs/ICS
  advisories. id, asset_id, advisory_id, matched_on (cpe/model+firmware/
  manual), status (open/acknowledged/compensating_control/patched/
  false_positive), notes
- **advisories** — cached CISA ICS advisory / CVE data. id, source
  (cisa_ics/nvd/manual), external_id, title, severity, affected_vendors_
  models (jsonb), published_at, raw_payload (jsonb)
- **exposure_scans** — a scan run. id, asset_id, scanned_at, method (shodan/
  censys/agent_probe/manual), result (exposed/not_exposed/unknown), raw_
  result (jsonb)
- **integrity_events** — a detected change. id, asset_id, event_type
  (credential_change/ip_change/firmware_change/config_upload/unknown_
  change), detected_at, detected_via, severity, acknowledged_by,
  acknowledged_at, notes
- **alerts** — user-facing notification derived from exposure_scans,
  asset_advisory_matches, or integrity_events. id, organization_id, source_
  type, source_id, severity, status (open/ack/resolved), channel_sent
  (email/sms/webhook), created_at
- **incident_playbooks** — belongs to organization. id, title, applies_to
  (site or asset_type), steps (jsonb ordered list), authorized_roles
- **manual_fallback_authorizations** — belongs to site. id, user_id,
  authorized_for (asset_id or site-wide), last_drilled_at, notes
- **audit_log** — append-only. id, organization_id, actor_id, action,
  subject_type, subject_id, occurred_at, metadata (jsonb) — every state
  change of consequence gets logged here, this table is a compliance
  feature in itself

Do not implement migrations yet. This is context for Parts 2 and 3.

## 7. Non-negotiable engineering standards

- Multi-tenancy scoping enforced at the query level (global scopes or
  policy classes), not just at the controller level — this is a security
  product, a tenant-isolation bug here is an existential product risk
- Every alert-generating code path needs a test proving it fires under the
  right conditions and does NOT fire under adjacent wrong conditions
- No secrets, API keys (Shodan/Censys/CISA feed credentials, etc.) hardcoded
  anywhere — .env only, and I will provide keys separately, do not stub
  fake ones into committed code
- Commit early and often once we start, with conventional commit messages
  (feat:, fix:, chore:, docs:) — this will be pushed to
  github.com/binsani/ot-exposure-monitor as we go, so commit history should
  read as a real build log, not one giant commit

---

END OF PART 1. Acknowledge you've read and understood this. Summarize back
the four modules and the core data model in your own words so I know it
landed correctly. Then STOP and WAIT for PART 2. Do not scaffold the
Laravel project yet.
```

---

## PART 2 of 3 — Module 1 (Exposure Scanning) & Module 2 (Asset Inventory + Vulnerability Matching)

```
This is PART 2 OF 3. Part 1 gave you the product vision, stack, and core
data model. This part goes deep on the first two of the four modules.

### STILL DO NOT WRITE CODE. WAIT FOR PART 3 BEFORE STARTING ANYTHING.

---

## MODULE 1 — Exposure Scanning

**Root cause this addresses:** utilities had zero visibility into which of
their PLCs were reachable from the public internet. This is the single
biggest lever in the whole product — most of these incidents were preventable
by simply knowing an asset was exposed and closing it.

### 1.1 Scan methods (support all three, asset-by-asset configurable)

- **Passive OSINT scanning (primary for cloud-only customers):** integrate
  with the Shodan API (and optionally Censys as a secondary source) to
  periodically check whether an organization's known public IP ranges or
  specific asset IPs appear in internet-wide scan results, and whether the
  fingerprint matches known OT/ICS device banners (e.g., Rockwell
  MicroLogix banner strings, Modbus/TCP port 502 open, EtherNet/IP port
  44818 open, etc.). Build a small library of known ICS port/banner
  signatures to check against — start with: Modbus TCP (502), EtherNet/IP
  (44818/2222), DNP3 (20000), BACnet (47808), and generic HTTP admin panels
  on common PLC/HMI vendor default ports.
- **Agent-based active probing (for customers who install the local
  agent):** a lightweight scheduled job running from inside the customer's
  network that checks whether each registered asset's IP is reachable from
  outside (e.g., via a callback-based check against a small cloud endpoint,
  or by checking firewall/NAT rule state if the agent has that access) —
  design this as a pluggable "exposure check driver" interface so we can
  add smarter methods later without redesigning the schema.
- **Manual entry / self-attestation:** for organizations not ready for
  automated scanning, allow an admin to manually mark an asset's exposure
  status with a note and a re-check reminder date. This must feel like a
  first-class, respected input, not a "lesser" fallback — many early
  customers will start here.

### 1.2 Scan scheduling & results

- Default scan cadence configurable per organization (recommend daily for
  Shodan/Censys checks, since those data sources themselves update roughly
  daily; more frequent doesn't add value and wastes API quota)
- Every scan run writes an `exposure_scans` row regardless of outcome
  (including "no change from last scan") so we have a full audit trail —
  do not just update the asset's current state and discard history
- **State-change detection is the core value, not the raw scan itself.**
  When an asset flips from not_exposed → exposed, or exposed → not_exposed,
  that transition must:
  - Create an `integrity_events` row (event_type = exposure_change)
  - Create an `alerts` row at high severity (exposure appearing is one of
    the most urgent things this product can tell someone)
  - Trigger the configured alert channel(s) immediately, not batched

### 1.3 UI/UX for this module

- Dashboard widget: "Assets currently exposed" count, prominent, red if >0
- Per-asset exposure history timeline (simple list: date, method, result)
- A dedicated "Exposure Map" view — even a simple sortable/filterable table
  is fine for v1, does not need to be a literal geographic map, though a
  site-level map pin showing "this site has N exposed assets" is a nice
  add if time allows (not required for v1)
- Clear, non-technical language for the primary persona (small-utility
  operator, not necessarily a security specialist): avoid jargon like
  "attack surface" in the main UI; use "This device can currently be
  reached from the internet" instead

---

## MODULE 2 — Asset Inventory & Vulnerability Matching

**Root cause this addresses:** utilities didn't know what PLC models/
firmware versions they had, so they had no way to know the FBI/CISA alert
about MicroLogix 1100/1400 applied to them specifically, until it was too
late.

### 2.1 Asset inventory management

- CRUD for assets as modeled in Part 1's data model
- Bulk import via CSV (utilities will often have this in a spreadsheet
  already — build a simple column-mapping import wizard, don't assume a
  fixed CSV schema)
- Asset detail page shows: identity info, current exposure status (pulled
  from Module 1), matched advisories (below), integrity event history
  (Module 3), and a free-text notes/compensating-controls field
- Support hierarchical grouping: site → process area (optional, e.g.
  "Intake," "Filtration," "Distribution") → asset, since utilities think in
  these terms and it'll matter for the incident playbook module too

### 2.2 Advisory / CVE ingestion

- Scheduled job polling the CISA ICS advisories feed (CISA publishes these
  at https://www.cisa.gov/news-events/cybersecurity-advisories — check for
  a structured feed/API rather than scraping HTML; if only RSS/HTML is
  available, build a resilient parser and flag it as a maintenance risk in
  your response)
- Store each advisory with enough structured metadata (affected vendor,
  affected model/product line, affected firmware version ranges where
  stated) to run automatic matching against the asset table
- Also support manual advisory entry, since not everything will parse
  cleanly and a human should be able to add "we heard about this one
  another way"

### 2.3 Matching logic

- Automatic match: when a new advisory is ingested (or a new/edited asset
  is saved), check vendor + model (+ firmware version range, if the
  advisory specifies one) against all assets in the organization, and
  create/update `asset_advisory_matches` rows
- This is a genuine "did we build this right" test target — write test
  cases specifically around the MicroLogix 1100/1400 CISA alert as a real
  fixture, since it's a live, current example: an asset with vendor=
  Rockwell/Allen-Bradley, model=MicroLogix 1100, should match an advisory
  scoped to that model, and should NOT match an advisory scoped only to,
  say, a Siemens S7 product line
- Each match gets a status the customer manages: open → acknowledged →
  (patched | compensating_control | false_positive). This status, plus the
  asset's notes field, is literally what an auditor or insurer will want to
  see, so make status changes require a note when moving to
  compensating_control or false_positive (short justification, not
  optional)

### 2.4 UI/UX for this module

- Org-level dashboard: count of open advisory matches by severity
- Per-advisory view: which assets are affected, org-wide, with quick bulk
  status update
- Per-asset view: full advisory match history, as described in 2.1
- A simple "Advisory Digest" — a weekly (configurable) summary of new
  advisories relevant to the organization's actual inventory, sent by
  email — this alone is a strong retention feature since it's ongoing
  value delivered with zero customer effort

---

END OF PART 2. Acknowledge you've read this and briefly summarize the scan
methods and the matching logic back to me. Then STOP and WAIT for PART 3,
which covers Module 3, Module 4, repo setup, and the actual go-ahead to
start building.
```

---

## PART 3 of 3 — Module 3 (Change/Integrity Alerting), Module 4 (Incident Playbook & Manual Fallback), Repo Setup, and Build Authorization

```
This is PART 3 OF 3 — the final part. You now have the full picture: vision,
stack, and data model from Part 1; exposure scanning and asset/vulnerability
inventory from Part 2; and here, the remaining two modules plus your actual
green light to begin work.

---

## MODULE 3 — Change & Integrity Alerting

**Root cause this addresses:** in the real incidents, attackers changed
operator passwords and reassigned device IPs, and nobody knew until the
plant stopped responding. This module is about catching that moment, not
after the fact.

### 3.1 What we're detecting

For agent-connected assets (this module has limited capability for
cloud-scan-only or manually-tracked assets — be explicit in the UI about
which tier of monitoring an asset has):

- **IP/network identity changes** — an asset's registered internal IP no
  longer resolves/responds the way it did last check, or responds from
  what appears to be a different device (fingerprint mismatch)
- **Credential/access changes** — where the local agent or a lightweight
  polling script can detect an authentication failure pattern change (e.g.,
  a previously-working service account login starts failing), flag it. Be
  honest in the UI copy that this is a heuristic, not a guarantee — we are
  not claiming to intercept the PLC's actual auth mechanism at the protocol
  level for v1, we are detecting the symptom (our monitoring can no longer
  authenticate the way it used to)
- **Firmware/config version drift** — where firmware version or a config
  checksum can be polled (varies heavily by device — build this as an
  optional per-asset capability, not an assumed one), flag any change
  against the last known-good baseline
- **Unexpected reachability change** — this overlaps with Module 1's
  exposure detection; wire them together rather than duplicating logic

### 3.2 Baseline & diffing approach

- On first successful check of an asset, establish a "baseline" snapshot
  (whatever fields are available: IP, firmware version if pollable, config
  checksum if pollable)
- Every subsequent check diffs against the current accepted baseline, not
  just the immediately previous check — this matters because slow drift
  across many small unremarkable-looking checks should still eventually
  trigger review
- When a customer reviews and accepts a change as legitimate (e.g., "yes,
  we did update firmware on Tuesday"), that becomes the new baseline and
  gets logged in `audit_log` with who approved it

### 3.3 Alert severity & routing

- Not everything is equally urgent. Suggested default severity mapping
  (make configurable per-organization later, hardcode sensible defaults for
  v1):
  - Credential/access anomaly on an internet-facing asset → Critical
  - IP/identity change on any asset → Critical
  - Exposure state change (from Module 1) → Critical
  - Firmware/config drift with no matching change-ticket/approval on file
    → High
  - New advisory match on an asset marked internet-facing → High
  - New advisory match on an internal-only asset → Medium
- Alert delivery: email at minimum for v1; design the notification system
  as a pluggable channel interface so SMS (e.g., via Twilio) and webhook
  (for customers with their own SOC/SIEM) can be added without refactoring
- Every alert must be acknowledgeable by a user, with the acknowledgment
  timestamped and logged — this closes the loop and matters for compliance
  reporting later

### 3.4 UI/UX for this module

- A unified "Alerts" inbox — do not build three separate alert lists for
  exposure/advisory/integrity, unify them with a filter, since a real
  operator during an incident needs one place to look
- Per-asset "health timeline" combining exposure history, integrity
  events, and advisory status changes in one chronological view — this is
  genuinely one of the more valuable screens in the product, worth getting
  right

---

## MODULE 4 — Incident Playbook & Manual Fallback Tracker

**Root cause this addresses:** when the PLCs went down, utilities had to
fall back to manual operation. Who's authorized to do that, whether it's
been drilled, and whether there's a documented procedure is exactly the
kind of thing regulators, insurers, and auditors ask about after an
incident — and most small utilities have this living in one senior
operator's head, not on paper.

### 4.1 Playbook management

- Organizations can author incident playbooks: a title, what it applies to
  (a specific asset type, a specific site, or org-wide), and an ordered
  list of steps (jsonb, each step has a short title + description + which
  role is responsible)
- Provide 2-3 starter/template playbooks out of the box, written generically
  enough to be broadly useful, covering: "PLC unresponsive / suspected
  lockout," "Confirmed exposure of internet-facing asset — immediate
  isolation," and "Manual fallback operation initiation" — these should be
  genuinely useful starting content, not placeholder text, since a new
  customer with zero playbooks authored is a customer who churns
- Playbooks are versioned (keep prior versions on edit, don't overwrite) —
  auditors care about what the playbook said at the time of an incident,
  not just what it says now

### 4.2 Manual fallback authorization tracking

- A simple register: which named individuals are authorized to take
  manual/physical control of which assets or sites, when that
  authorization was granted, and when it was last drilled/tested
- A "drill" is loggable as an event (date, who participated, notes,
  duration) — this becomes evidence of operational readiness, which is
  exactly what insurers underwriting cyber policies for critical
  infrastructure are starting to ask for
- Simple staleness indicator: flag authorizations that haven't been drilled
  in over a configurable period (default 12 months) — this is a low-effort,
  high-perceived-value feature, prioritize it

### 4.3 Incident logging (ties everything together)

- When a real incident occurs, allow a user to open an "incident record"
  that can attach: the relevant alerts (from Module 3), the playbook that
  was followed (or note that none was, and what was improvised instead),
  a timeline of manual actions taken, and a final resolution note
- This incident record is the single most valuable object for a post-
  incident report to management, regulators, or an insurer — it should be
  exportable as a clean PDF (use a Laravel PDF package, or a simple
  print-friendly view if PDF export is deferred past v1 — your call, flag
  the tradeoff)

---

## Repository & Delivery Setup

- Create the repository at `github.com/binsani/ot-exposure-monitor` —
  private, with a proper `.gitignore` for Laravel/Vue, a `README.md`
  summarizing the product (you can draft this from Part 1's vision
  section), and this file committed at `/docs/PRODUCT_SPEC.md`
- Set up a `develop` branch for ongoing work and keep `main` for
  stable/deployable states, even as a solo-developer project — this is a
  commercial product, not a personal script, treat the git history and
  branch discipline accordingly
- Environment config via `.env.example` with placeholders (never real
  keys) for: `SHODAN_API_KEY`, `CENSYS_API_ID`, `CENSYS_API_SECRET`,
  `CISA_ADVISORY_FEED_URL`, mail driver config, and queue/Redis config
- Set up CI (GitHub Actions is fine) running Pest and Vitest on push — even
  a minimal pipeline is worth having from day one on a security product

## Build Order (this is the actual sequence — follow it)

1. Laravel + Vue + Inertia scaffold, auth, multi-tenancy foundation,
   organizations/users/sites/assets migrations from Part 1
2. Module 2 core (asset inventory CRUD + CSV import) — do this before
   Module 1, since exposure scanning needs assets to exist first
3. Module 1 (exposure scanning) — start with manual entry + Shodan
   integration, agent-based probing can follow once the cloud path works
4. Advisory ingestion + matching logic (rest of Module 2), including the
   MicroLogix 1100/1400 test fixture described in Part 2
5. Module 3 (integrity alerting), building on the exposure_scans and
   asset_advisory_matches data already flowing
6. Module 4 (playbooks + manual fallback tracker + incident records)
7. Alerts inbox UI unifying everything, dashboard polish, weekly digest
   email

---

### YOU NOW HAVE ALL 3 PARTS.

Before writing a single line of code:
1. Give me a brief written confirmation of your understanding of the full
   product across all three parts.
2. Flag any real architectural concerns or open questions now — this is
   your last checkpoint before implementation starts.
3. Once I respond "confirmed, begin," proceed with Build Order step 1.

Do not scaffold anything until I explicitly say "confirmed, begin."
```