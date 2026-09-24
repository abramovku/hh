<?php

/*
|--------------------------------------------------------------------------
| New call flow (ТЗ «ПК — обзвон»)
|--------------------------------------------------------------------------
|
| FLOW_MODE=legacy keeps the current behaviour untouched.
| FLOW_MODE=new    routes Estaff / Twin voice webhooks and the scheduler to
|                  the new flow (app/Services/Flow, app/Jobs/Flow).
| FLOW_MODE=hybrid legacy for everyone except candidates whose Estaff vacancy id is in
|                  FLOW_NEW_VACANCY_IDS — those go through the new flow (App\Services\Flow\FlowRouter).
|
| Changing the mode requires `php artisan config:clear` and `queue:restart`.
*/

$csv = static function (string $key, string $default = ''): array {
    return array_values(array_filter(array_map('trim', explode(',', (string) env($key, $default)))));
};

return [
    'mode' => env('FLOW_MODE', 'legacy'),

    // hybrid mode: Estaff vacancy ids handled by the new flow (everything else stays legacy).
    'new_vacancy_ids' => $csv('FLOW_NEW_VACANCY_IDS'),

    // Business timezone for "today", call windows and "next day". App timezone is UTC.
    'timezone' => env('FLOW_TIMEZONE', 'Europe/Moscow'),

    // 3.1 — Estaff vacancy position_id values that are allowed for processing.
    'allowed_position_ids' => $csv('ESTAFF_ALLOWED_POSITION_IDS'),

    // 4.4 / 4.5 — vacancies that use the "old script" bot instead of the cold one.
    'old_script_vacancy_ids' => $csv('FLOW_OLD_SCRIPT_VACANCY_IDS', '7541291626956944847'),

    'urls' => [
        'autocall' => env('TWIN_AUTOCALL_URL', 'https://cis.twin24.ai/api/v1/telephony/autoCall'),
        'autocall_candidate' => env('TWIN_AUTOCALL_CANDIDATE_URL', 'https://cis.twin24.ai/api/v1/telephony/autoCallCandidate'),
        'analyse_sessions' => env('TWIN_ANALYSE_URL', 'https://analyse.twin24.ai/api/v1/search/cis/sessions'),
    ],

    /*
    | Twin autoCall task types. Key is stored in call_tasks.type.
    | {date} in the name is replaced using date_format (business timezone).
    | from/to — allowCallTimeFrom/To in seconds since midnight.
    */
    'tasks' => [
        'warm' => [
            'name' => 'ПК - Теплый отклик - {date}',
            'bot' => env('TWIN_BOT_WARM', '031e44f5-76b6-4442-a109-09e0654582ea'),
            'from' => 36000,
            'to' => 79200,
            'date_format' => 'd.m.Y',
        ],
        'cold' => [
            'name' => 'ПК - Холодный отклик - {date}',
            'bot' => env('TWIN_BOT_COLD', '093f6415-58f8-4bb5-ac93-0bfb4389f23e'),
            'from' => 36000,
            'to' => 79200,
            'date_format' => 'd.m.Y',
        ],
        'old_script' => [
            'name' => 'ПК - Старый скрипт - {date}',
            'bot' => env('TWIN_BOT_OLD', 'e227cd98-266a-4218-bd07-45a125e814d9'),
            'from' => 36000,
            'to' => 79200,
            'date_format' => 'd.m.Y',
        ],
        'reminder' => [
            'name' => 'ПК_Напоминание {date}',
            'bot' => env('TWIN_BOT_REMINDER', 'eb314aa3-ceb5-4146-abc0-f4daf14332a6'),
            'from' => 34200,
            'to' => 61200,
            'date_format' => 'd.m',
        ],
        'feedback' => [
            'name' => 'ПК_ОС {date}',
            'bot' => env('TWIN_BOT_FEEDBACK', '3d4c8b21-6924-424b-8430-0cf1aa14de6e'),
            'from' => 34200,
            'to' => 61200,
            'date_format' => 'd.m',
        ],
    ],

    // Seconds to wait after a brand-new autoCall is created before adding candidates (legacy did sleep(6)).
    'sleep_after_create' => (int) env('FLOW_SLEEP_AFTER_CREATE', 6),

    // 5.3 — Twin analyse sessions lookup. Retried via delayed re-dispatch when no items are returned yet.
    'analyse' => [
        'fields' => 'messagesAsString,results,currentStatusName',
        'retry_delay' => (int) env('FLOW_ANALYSE_RETRY_DELAY', 60),
        'max_attempts' => (int) env('FLOW_ANALYSE_MAX_ATTEMPTS', 3),
    ],

    'lead_confirmation' => 'ПК_Лид',
    'lead_state' => 'event_type_49:scheduled',
    'reminder_confirmation' => 'ПК_Напоминание-Время',

    'states' => [
        'not_answered' => 'event_type_35',
        'before_call' => 'event_type_88',
    ],

    /*
    | 6.4 — Estaff candidate fields. Current state is `state_id`.
    | The interview date is read from candidate `events[]` (event of the lead state, its `date`).
    | Estaff `state_date` is the *transition* date, not the interview date, so no date field is requested
    | by default; set FLOW_ESTAFF_STATE_DATE_FIELD only if a dedicated field appears.
    */
    'estaff_state_fields' => [
        'state' => env('FLOW_ESTAFF_STATE_FIELD', 'state_id'),
        'state_date' => env('FLOW_ESTAFF_STATE_DATE_FIELD', ''),
    ],

    /*
    | 5.4 — «СТАТУСЫ БОТА»: results.confirmation → Estaff state_id.
    | Source: https://docs.google.com/spreadsheets/d/1iONRhEtbEhcuvyztLoY8AHxgZO68kxRWjQJWIt6Z1Io
    | ПК_Лид is handled separately (eventCandidate with lead_state),
    | ПК_Напоминание-Время by the reminder flow (6.5). Unknown confirmations are logged and skipped.
    */
    'confirmation_states' => [
        'ПК_Отказ кандидата' => 'event_type_46',
        'ПК_Отказ кандидата-Ошиблись' => 'event_type_46',
        'ПК_Отказ кандидата-ПД' => 'event_type_46',
        'ПК_Отказ кандидата-Думает' => 'event_type_46',
        'ПК_Наш отказ-Тишина' => 'event_type_46',
        'ПК_Отказ кандидата-Неактуально' => 'event_type_46',
        'ПК_Отказ кандидата-Адрес' => 'event_type_46',
        'ПК_Сброс' => 'event_type_46',
        'ПК_Напоминание-Неактуально' => 'event_type_46',

        'ПК_Отказ кандидата-НеактуальноТЦ' => 'event_type_81',

        'ПК_Наш отказ-Негатив' => 'event_type_45',
        'ПК_Наш отказ-Записан на собеседование' => 'event_type_45',
        'ПК_Наш отказ-Возраст' => 'event_type_45',
        'ПК_Наш отказ-ВозрастБ' => 'event_type_45',
        'ПК_Наш отказ-ВозрастМ' => 'event_type_45',
        'ПК_Наш отказ-Судимость' => 'event_type_45',
        'ПК_Наш отказ-Гражданство' => 'event_type_45',
        'ПК_Наш отказ-Город' => 'event_type_45',
        'ПК_Наш отказ-Закрыт ТЦ' => 'event_type_45',

        'ПК_Резерв' => 'event_type_43',
        'ПК_Лимит отработок' => 'event_type_83',
        'ПК_Отказ кандидата-Оператор' => 'event_type_82',
    ],

    // «Ничего не делать» column of the same table: known results that must not change the Estaff state.
    'ignored_confirmations' => [
        'ПК_Перезвон',
        'ПК_Автоответчик',
    ],
];
