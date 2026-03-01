# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 12 recruitment automation system integrating three external APIs:
- **HeadHunter (HH)** — job board API for vacancies and candidate responses
- **Estaff** — internal candidate management system
- **Twin24** — automated calling and messaging (WhatsApp, SMS, Voice)

## Commands

```bash
composer dev          # Start all services: PHP server, queue worker, pail logs, Vite
composer test         # Clear config cache and run PHPUnit
.\pint.bat            # Fix all code style issues (use pint.sh on Linux)
.\pint.bat --test     # Check style without fixing
.\pint.bat --dirty    # Only check uncommitted files
php artisan queue:listen --tries=1  # Queue worker (jobs self-manage retries)
php artisan pail --timeout=0        # Live log viewer
```

To run a single test:
```bash
php artisan test --filter TestClassName
```

## Architecture

### Service Layer Pattern
Each integration follows a two-class pattern in `app/Services/*/`:
1. **Service class** (`HH.php`, `Twin.php`, `Estaff.php`) — business logic
2. **Client class** (`HHClient.php`, `TwinClient.php`, `EstaffClient.php`) — HTTP communication with OAuth2/token refresh

Services are bound via ServiceProviders and resolved as: `app('hh')`, `app('twin')`, `app('estaff')`.

### Data Flow
`Webhook → WebhookController → Job (queued) → Service → External API`

Estaff webhooks carry candidate state changes (`event_type_*`). `WebhookController::estaffWebhooks()` dispatches jobs based on the event type:
- `event_type_47` → `StartTwinCall`
- `event_type_44` → `StartTwinSms`
- `event_type_48` → `StartTwinColdConversation`
- `event_type_32` → `StartTwinManualConversation`

Twin webhooks (`OperateTwinWebhook`, `OperateTwinVoiceWebhook`) poll status and self-delete from the queue on final status.

### Console Commands (`app/Console/Commands/`)
- `HHAuth` / `HHMe` — OAuth flow and user info
- `HHSync` — fetch new HH responses and sync to Estaff
- `EstaffSync` — sync data from Estaff
- `EstaffSetupWebhook` / `EstaffAutoWebhook` — manage Estaff webhook registration
- `LogRotate` — rotate log files

## Key Conventions

### Logging
Every service method logs before and after external API calls using the appropriate channel:
```php
Log::channel('hh')->info(__FUNCTION__ . ' send', ['id' => $id]);
$data = $this->HHClient->get('/endpoint');
Log::channel('hh')->info(__FUNCTION__ . ' get', ['data' => $data]);
```
Channels: `app`, `hh`, `twin`, `estaff` — defined in `config/logging.php`.

### OAuth Token Management
HH and Twin clients auto-refresh expired tokens:
- Catch 403 (HH) or 401 (Twin) → call `$this->auth()` → retry once
- Tokens stored as JSON in the `settings` table under key `hh_credentials`
- Always use `config('services.hh.*')` — never hardcode credentials

### Job Queue Self-Management
Jobs track their own queue entries via `TwinTask` (links `candidate_id`, `chat_id`, `job_id` UUID). When a Twin status is final, the job deletes itself:
```php
DB::table('jobs')->where('payload', 'like', '%' . $task->job_id . '%')->whereNull('reserved_at')->delete();
```
Always create a `TwinTask` record when dispatching a new Twin job.

### CallTask Deduplication
Check `CallTask` table before creating a new call task — they are created at most once per day per type.

### Phone Number Sanitization
Strip all formatting before passing to Twin24:
```php
$phone = str_replace(['+', '(', ')', '-', ' '], '', $phone);
```

### Form Requests
All API endpoints use `FormRequest` classes in `app/Http/Requests/`. They override `failedValidation()` and `expectsJson()` to return JSON errors.

### Controller Error Handling
Wrap all external service calls in try-catch and return a consistent JSON error shape:
```php
try {
    $response = app('estaff')->someMethod($request->all());
} catch (\Exception $e) {
    Log::channel('app')->error('...', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    return response()->json(['success' => false, 'message' => '...', 'error' => $e->getMessage(), 'data' => []]);
}
```

## Database

- `responses` — HH candidate responses; columns `vacancy_estaff`/`candidate_estaff` are nullable until synced
- `settings` — generic key-value store (critical key: `hh_credentials`)
- `jobs` — Laravel queue table
- `twin_tasks` — tracks active Twin jobs for status polling
- `call_tasks` — deduplicates Twin call task creation

## Common Pitfalls

- **ID namespaces are separate** — never use Estaff IDs with HH API or vice versa; map via `responses` table
- **WhatsApp vs SMS differ** — `Twin::sendMessage()` uses `chatId`/`botId`; SMS uses a different structure; check both before modifying
- **Queue driver is `database` with `--tries=1`** — jobs must not rely on Laravel's built-in retry; use delayed self-dispatch instead
- **SSL verification is disabled** in HH/Twin clients (`CURLOPT_SSL_VERIFYPEER => false`) — required for current environment
- **Hardcoded values** — some bot IDs, SMS texts, and job types are hardcoded in `Twin.php`; extract to config before changing

## Code Style

Configured via `pint.json` (Laravel preset). Key enforced rules: `no_unused_imports`, `ordered_imports` (alpha), `single_quote`, `blank_line_before_statement` (return), `simplified_null_return`.
