# GitHub Copilot Instructions

## Project Overview
Laravel 12 recruitment automation system integrating three external APIs:
- **HeadHunter (HH)**: Job board API for vacancies and candidate responses
- **Estaff**: Internal candidate management system
- **Twin24**: Automated calling and messaging platform (WhatsApp, SMS, Voice)

## Architecture

### Service Layer Pattern
Services follow a consistent two-class pattern (see `app/Services/*/`):
1. **Service class** (`HH.php`, `Twin.php`, `Estaff.php`) - Business logic methods
2. **Client class** (`HHClient.php`, `TwinClient.php`, `EstaffClient.php`) - HTTP communication with OAuth2/token refresh

Services are bound via service providers (e.g., `HHServiceProvider`) and resolved using `app('hh')`, `app('twin')`, `app('estaff')`.

### Data Flow: Webhook → Job → Service → API
1. **Estaff webhook** receives candidate state change (e.g., `event_type_47` = "ready for call")
2. **WebhookController** dispatches appropriate Job to queue
3. **Job** (e.g., `StartTwinCall`) fetches data via Estaff service, processes, calls Twin service
4. **Twin webhook** receives status updates, may create delayed retry jobs via `OperateTwinWebhook`

Critical: Jobs are **queued asynchronously** - state changes trigger workflows, not synchronous responses.

## Key Conventions

### Logging Pattern
Every service method logs before/after external API calls:
```php
Log::channel('hh')->info(__FUNCTION__ . ' send', ['id' => $id]);
$data = $this->HHClient->get('/endpoint');
Log::channel('hh')->info(__FUNCTION__ . ' get', ['id' => $id]);
```

Channels: `app`, `hh`, `twin`, `estaff` (see `config/logging.php`). Use appropriate channel when adding service methods.

### State-Based Workflow
Candidate states (`event_type_*`) trigger specific actions in `WebhookController::estaffWebhooks()`:
- `event_type_47`: Start automated call
- `event_type_44`: Send SMS
- `event_type_48`: Start cold conversation
- `event_type_32`: Start manual conversation

When adding new workflows, follow this switch-case pattern.

### OAuth Token Management
HH and Twin clients auto-refresh expired tokens:
- Catch 403 (HH) or 401 (Twin) HTTP errors
- Call `$this->auth()` with refresh token
- Retry original request once
- Store credentials in `settings` table (key: `hh_credentials`)

Never hardcode tokens - always use `config('services.hh.client_id')` etc.

### Phone Number Sanitization
Always strip formatting before API calls:
```php
$phone = str_replace(['+', '(', ')', '-', ' '], '', $candidateData['candidate']['mobile_phone']);
```

## Critical Patterns

### Job Queue Management
Jobs self-manage their queue entries. Pattern in `OperateTwinWebhook`:
1. Check Twin message status
2. If status is final (not PENDING/DELAYED/etc.), remove job from queue:
```php
DB::table('jobs')->where('payload', 'like', '%' . $task->job_id . '%')->whereNull('reserved_at')->delete();
```
3. Track jobs via `TwinTask` model linking `candidate_id`, `chat_id`, and `job_id` (UUID)

When creating new Twin jobs, always create corresponding `TwinTask` record.

### Form Request Validation
All API endpoints use custom FormRequest classes (see `app/Http/Requests/`):
- Return JSON errors automatically via `failedValidation()` override
- Set `expectsJson(): bool` to `true` for proper API response format

### External API Error Handling
Pattern in `EndpointController`:
```php
try {
    $EstaffService = app('estaff');
    $response = $EstaffService->someMethod($request->all());
} catch (\Exception $e) {
    Log::channel('app')->error('Estaff service error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    return response()->json([
        'success' => false,
        'message' => 'Estaff return error.',
        'error' => $e->getMessage(),
        'data' => [],
    ]);
}
```

Always catch and log external API failures with full context.

## Development Commands

### Start Development Environment
```bash
composer dev
# Runs concurrently: PHP server, queue listener, log viewer (pail), Vite
```

### Run Specific Components
```bash
php artisan queue:listen --tries=1  # Queue worker (no retries)
php artisan pail --timeout=0        # Live log viewer
npm run dev                         # Vite asset bundler
```

### Testing
```bash
composer test  # Clears config cache and runs PHPUnit
```

### Code Style
```bash
.\pint.bat          # Fix all code style issues
.\pint.bat --test   # Check without fixing
.\pint.bat --dirty  # Only check uncommitted files (Git)
```

Laravel Pint configuration in `pint.json` enforces Laravel preset with additional rules for imports and spacing.

## Database Patterns

### Response Tracking
`responses` table tracks HH candidate responses:
- `response_id`: HeadHunter response ID
- `vacancy_id`/`manager_id`: HH identifiers
- `vacancy_estaff`/`candidate_estaff`: Estaff system IDs (nullable until created)
- `sent_at`: Timestamp when sent to Estaff
- `error`: Nullable error field for failed syncs

Use `setSend()` method to mark response as sent.

### Settings Storage
Generic key-value store in `settings` table. Critical keys:
- `hh_credentials`: OAuth tokens (access_token, refresh_token) stored as JSON

## Common Pitfalls

1. **Don't use Estaff IDs for HH API calls** - Systems use different ID namespaces. Always map via `responses` table or service methods like `findVacancy()`.

2. **Twin message types differ** - WhatsApp chat uses `chatId`/`botId`, SMS uses different structure. Check `Twin::sendMessage()` vs `Twin::sendSms()` for patterns.

3. **CallTask caching** - Twin call tasks are created once per day per type. Check `CallTask` table before creating new task to avoid duplicates.

4. **SSL verification disabled** - Clients have `CURLOPT_SSL_VERIFYPEER => false` in HH/Twin clients. Security risk noted but required for current environment.

5. **Hardcoded values exist** - Bot IDs, SMS text, and job types are hardcoded in `Twin.php`. Extract to config before modifying.

## Integration Endpoints

### Webhooks (No Auth - Security Gap)
- `POST /api/estaff-webhooks`: Estaff state changes
- `POST /api/twin-webhooks`: Twin message status updates
- `POST /api/twin-webhooks-voice`: Twin voice call events

### Twin Bot API (No Auth - Security Gap)
- `POST /api/twin/createCandidate`: Create candidate in Estaff from chat
- `POST /api/twin/updateCandidate`: Update candidate data
- `POST /api/twin/stateCandidate`: Change candidate state
- `POST /api/twin/getCandidate`, `findCandidate`: Retrieve candidates
- `POST /api/twin/getVacancy`, `findVacancy`: Retrieve vacancies

**Critical**: All API endpoints lack authentication middleware. Implement `auth:sanctum` or webhook signature verification before production use.

## When Adding Features

1. **New service method**: Add logging, handle OAuth refresh, return array (never throw in service layer)
2. **New job**: Implement `ShouldQueue`, add logging, create with UUID, handle in webhook if needed
3. **New webhook event**: Add case in `WebhookController::estaffWebhooks()`, dispatch appropriate job
4. **New API endpoint**: Create FormRequest validator, use try-catch in controller, log all actions
5. **New external API**: Create Service + Client pair, add ServiceProvider binding, add config in `services.php`
