# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 12 recruitment automation system integrating three external APIs:
- **HeadHunter (HH)** — job board API for vacancies and candidate responses
- **Estaff** — internal candidate management system
- **Twin24** — automated calling and messaging (WhatsApp, SMS, Voice)
- **Location** — region lookup by phone number (`GET {LOCATION_API_URL}/region?number=7XXXXXXXXXX`), returns Estaff `location_id`

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

Services are bound via ServiceProviders and resolved as: `app('hh')`, `app('twin')`, `app('estaff')`, `app('location')`.

`app('location')` never throws: `locationId($phone)` returns the Estaff `location_id` string or `null` (not matched / API error / not configured), logging failures to the `location` channel. Phone is normalized to 11 digits without `+` before the request.

### Data Flow
`Webhook → WebhookController → Job (queued) → Service → External API`

Estaff webhooks carry candidate state changes (`event_type_*`). `WebhookController::estaffWebhooks()` dispatches jobs based on the event type:
- `event_type_47` → `StartTwinCall`
- `event_type_44` → `StartTwinSms`
- `event_type_48` → `StartTwinColdConversation`
- `event_type_32` → `StartTwinManualConversation`

Twin webhooks (`OperateTwinWebhook`, `OperateTwinVoiceWebhook`) poll status and self-delete from the queue on final status.

### Flow switch (`FLOW_MODE=legacy|new|hybrid`, `config/flow.php`)
The routes `estaff-webhooks` and `twin-webhooks-voice` point to `FlowSwitchController` (validation) → `App\Services\Flow\FlowRouter`
(the only place deciding legacy vs new) → `WebhookController::handleState()` (legacy, described above) or `FlowWebhookController::handleState()`.
Before routing, `App\Services\Estaff\WebhookDeduplicator` drops repeated `candidate_state` webhooks in every mode: key = event type +
`candidate_id` + `state_id` + `vacancy_id`, kept in the cache for `ESTAFF_WEBHOOK_DEDUP_TTL` seconds (default a week, `0` = off). Logged as
`Webhook ignored: duplicate` in the `estaff` channel. The key is set on receipt, so a failed job is retried via `queue:retry`, not by a repeated webhook.
- `hybrid`: new flow only for candidates whose Estaff vacancy id is in `FLOW_NEW_VACANCY_IDS`. States `new`/`47`/`48` are routed by
  `data.vacancy_id`; without it `ResolveVacancyAndRoute` reads `main_vacancy_id` from Estaff. Other states go to legacy (plus cancelling
  `interview_schedules`). Twin voice webhooks are routed by `botId` (new-flow bots) or `taskId`/`autoCallId` → `call_tasks.type`;
  `CANDIDATE_CHANGED` for new-flow calls and `CALL_ENDED` for legacy calls are ignored. `EstaffSync` skips new-flow vacancies (`error = 'new flow vacancy'`).
Legacy code stays behaviourally untouched (only `handleState()` extraction and the EstaffSync skip); do not add new-flow logic to it.
Switching the mode needs `config:clear` + `queue:restart`.

New flow (`app/Services/Flow`, `app/Jobs/Flow`, `App\Support\Flow` helper):
- Only Estaff states `new` / `event_type_47` / `event_type_48` are processed. `CandidateGuard` (ТЗ 3.1) finds candidates by phone,
  resolves vacancy `position_id`, allows only `ESTAFF_ALLOWED_POSITION_IDS`, selects index 0, logs duplicates.
- `StartFlowCall` → `Twin::getAutoCall($taskKey)` (one autoCall per business-day per key: `warm|cold|old_script|reminder|feedback`,
  stored in `call_tasks`, guarded by `Cache::lock` + unique `(date,type)`) → `addCandidateToAutoCall()` (with `clientExternalId`) → `event_type_88`.
  Cold calls also fill `location_id` via `CandidateLocationEnricher`; `EndpointController::create/update` do the same when the flag is on.
- `ProcessCallEnded` / `CallResultProcessor` (ТЗ 5): task type by `botId`; not `ANSWERED` or old-script bot → `event_type_35`;
  otherwise `Twin::findSessions()` → `results.confirmation` → `config('flow.confirmation_states')` (user-maintained table) or `LeadHandler`
  (`ПК_Лид` → `add_event` with `user_login`, retried without it). No session yet → delayed self-dispatch, not `release()`.
- Leads are stored in `interview_schedules`; `app:flow-reminders` (every 15 min, 09:30–17:00 business tz) and `app:flow-feedback`
  add them to the `ПК_Напоминание {date}` / `ПК_ОС {date}` autoCalls. Any Estaff state other than `event_type_49*` cancels active rows.
- All "today"/window logic uses `Flow::timezone()` (`FLOW_TIMEZONE`, default Europe/Moscow); app timezone is UTC.
- Estaff API confirmed (Websoft docs + DB dictionary https://office.datex.ru/download/5.1/EStaff_DB_51.htm): `candidate/find` → `candidates`,
  candidate `state_id` / `main_vacancy_id` / `location_id` (also accepted in `candidate/change` `changed_data`), vacancy `position_id`,
  `set_state` accepts `event.comment`, `add_event` takes `candidate{id,state_id}`, required `vacancy{id}`, `event{date,comment,user_login}`.
  Estaff `state_date` is the *transition* date, so the interview date for ТЗ 6.4 is read from candidate `events[]`
  (`type_id` + `occurrence_id` of the lead state).
- Twin `POST telephony/autoCallCandidate` takes a single candidate object (`autoCallId`, `phone[]`, `variables`, `callbackData`,
  `clientExternalId`, `forceStart`); the `batch: [...]` wrapper from ТЗ 4.6 is only for the legacy `/batch` endpoint and gives HTTP 400.

### Console Commands (`app/Console/Commands/`)
- `HHAuth` / `HHMe` — OAuth flow and user info
- `HHSync` — fetch new HH responses and sync to Estaff (scheduled only in legacy mode)
- `EstaffSync` — sync data from Estaff (scheduled only in legacy mode)
- `EstaffSetupWebhook` / `EstaffAutoWebhook` — manage Estaff webhook registration
- `Flow/SendInterviewReminders` (`app:flow-reminders {--date=}`) / `Flow/SendFeedbackCalls` (`app:flow-feedback {--date=}`) — new flow, scheduled only in new mode
- `LogRotate` — rotate log files

## Key Conventions

### Logging
Every service method logs before and after external API calls using the appropriate channel:
```php
Log::channel('hh')->info(__FUNCTION__ . ' send', ['id' => $id]);
$data = $this->HHClient->get('/endpoint');
Log::channel('hh')->info(__FUNCTION__ . ' get', ['data' => $data]);
```
Channels: `app`, `hh`, `twin`, `estaff`, `location` — defined in `config/logging.php`.

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
- `call_tasks` — deduplicates Twin call task creation; unique `(date, type)`. Legacy `type` = `TWIN_CALL_TYPE`, new flow `type` = task key
- `interview_schedules` — new flow leads (`ПК_Лид`): interview date, stage (`scheduled → reminder_sent → feedback_pending → feedback_sent`, or `reminder_skipped|cancelled|superseded`), autoCall ids

Tests run on in-memory SQLite: MySQL-only statements in migrations (`FULLTEXT`, `UPDATE ... JOIN`) are guarded by driver checks.

## Common Pitfalls

- **ID namespaces are separate** — never use Estaff IDs with HH API or vice versa; map via `responses` table
- **WhatsApp vs SMS differ** — `Twin::sendMessage()` uses `chatId`/`botId`; SMS uses a different structure; check both before modifying
- **Queue driver is `database` with `--tries=1`** — jobs must not rely on Laravel's built-in retry; use delayed self-dispatch instead
- **SSL verification is disabled** in HH/Twin clients (`CURLOPT_SSL_VERIFYPEER => false`) — required for current environment. Location client too (`LOCATION_API_VERIFY_SSL=false`): the region API cert does not match its IP host, and its `http://` URL 301-redirects to `https://`
- **Hardcoded values** — some bot IDs, SMS texts, and job types are hardcoded in `Twin.php`; extract to config before changing

## Code Style

Configured via `pint.json` (Laravel preset). Key enforced rules: `no_unused_imports`, `ordered_imports` (alpha), `single_quote`, `blank_line_before_statement` (return), `simplified_null_return`.
