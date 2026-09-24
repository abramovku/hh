# HH Estaff - Система автоматизации рекрутинга

Система автоматизации процессов найма и работы с кандидатами, интегрирующая три внешних API для полного цикла рекрутинга.

## Описание проекта

Laravel 12 приложение для автоматизации процессов рекрутинга через интеграцию:
- **HeadHunter API** - получение откликов на вакансии, работа с резюме
- **Estaff** - внутренняя система управления кандидатами и вакансиями
- **Twin24** - автоматизированные звонки, WhatsApp чаты и SMS-рассылки

## Основные возможности

### Автоматизация работы с кандидатами
- Автоматическое получение откликов с HeadHunter
- Синхронизация данных кандидатов между HH и Estaff
- Автоматические звонки кандидатам при изменении статуса
- WhatsApp чаты для общения с кандидатами
- SMS-уведомления о вакансиях

### Webhook-based архитектура
- Получение событий изменения статуса кандидата из Estaff
- Автоматический запуск соответствующих действий (звонок, SMS, чат)
- Отслеживание статусов сообщений и звонков через Twin24 webhooks
- Автоматические повторные попытки при ошибках

### API для Twin24 бота
- Создание и обновление кандидатов из WhatsApp чата
- Поиск вакансий по фильтрам
- Изменение статусов кандидатов
- Получение информации о кандидатах и вакансиях

## Технологический стек

- **Laravel 12** - PHP фреймворк
- **PHP 8.2** - язык программирования
- **SQLite** - база данных (по умолчанию)
- **Laravel Pint** - стандартизация кода
- **Laravel Pail** - просмотр логов в реальном времени
- **Vite** - сборка фронтенд ассетов
- **Guzzle** - HTTP клиент для работы с API

## Установка

### Требования
- PHP 8.2+
- Composer
- Node.js & NPM
- SQLite (или MySQL/PostgreSQL)

### Установка зависимостей

```bash
# Установка PHP зависимостей
composer install

# Установка Node.js зависимостей
npm install

# Копирование .env файла
copy .env.example .env

# Генерация ключа приложения
php artisan key:generate

# Запуск миграций
php artisan migrate
```

### Настройка переменных окружения

Отредактируйте `.env` файл и добавьте настройки для внешних сервисов:

```env
# HeadHunter API
HH_API_URL=https://api.hh.ru
HH_API_AUTH_URL=https://hh.ru/oauth/token
HH_CLIENT_ID=your_client_id
HH_CLIENT_SECRET=your_client_secret
HH_REDIRECT_URI=your_redirect_uri
HH_EMPLOYER=your_employer_id

# Estaff API
ESTAFF_API_URL=https://your-estaff-url.com/api
ESTAFF_TOKEN=your_estaff_token

# Twin24 API
TWIN_AUTH_URL=https://api.twin24.ai/auth
TWIN_AUTH_LOGIN=your_login
TWIN_AUTH_PASSWORD=your_password
TWIN_CHAT_ID=your_chat_id
TWIN_BOT_ID=your_bot_id
TWIN_PROVIDER_ID=your_provider_id

# External URL для webhooks
APP_EXTERNAL_URL=https://your-domain.com
```

### Авторизация в HeadHunter

```bash
php artisan hh:auth
```

Выполните команду и следуйте инструкциям для получения OAuth токена.

## Разработка

### Запуск среды разработки

```bash
# Запуск всех сервисов одновременно
composer dev
```

Эта команда запускает:
- PHP development server (порт 8000)
- Queue worker (обработка фоновых задач)
- Pail (логи в реальном времени)
- Vite (hot reload для фронтенда)

### Запуск отдельных компонентов

```bash
# PHP сервер
php artisan serve

# Queue worker
php artisan queue:listen --tries=1

# Просмотр логов
php artisan pail --timeout=0

# Vite dev server
npm run dev
```

## Тестирование

```bash
# Запуск тестов
composer test

# Запуск с покрытием
php artisan test --coverage
```

## Стиль кода

Проект использует Laravel Pint для единообразного стиля кода.

```bash
# Исправить все проблемы стиля
.\pint.bat

# Проверить без исправления
.\pint.bat --test

# Только измененные файлы (Git)
.\pint.bat --dirty
```

## Структура проекта

```
app/
├── Console/Commands/     # Artisan команды (синхронизация, webhooks)
├── Http/
│   ├── Controllers/     # API контроллеры
│   └── Requests/        # Form Request validators
├── Jobs/                # Асинхронные задачи (звонки, SMS, чаты)
├── Models/              # Eloquent модели
└── Services/            # Сервисы для работы с внешними API
    ├── HH/             # HeadHunter сервис + клиент
    ├── Estaff/         # Estaff сервис + клиент
    └── Twin/           # Twin24 сервис + клиент
```

## API Endpoints

### Webhooks
- `POST /api/estaff-webhooks` - события от Estaff
- `POST /api/twin-webhooks` - статусы сообщений Twin24
- `POST /api/twin-webhooks-voice` - статусы звонков Twin24

### Twin24 Bot API
- `POST /api/twin/createCandidate` - создать кандидата
- `POST /api/twin/updateCandidate` - обновить данные
- `POST /api/twin/stateCandidate` - изменить статус
- `POST /api/twin/getCandidate` - получить кандидата по ID
- `POST /api/twin/findCandidate` - поиск кандидатов
- `POST /api/twin/getVacancy` - получить вакансию по ID
- `POST /api/twin/findVacancy` - поиск вакансий

## Workflow процессы

### Обработка откликов с HeadHunter
1. Команда `hh:sync` получает новые отклики
2. Отклики сохраняются в таблицу `responses`
3. Данные кандидата отправляются в Estaff через API
4. Связка HH ID ↔ Estaff ID сохраняется

### Автоматический звонок кандидату
1. В Estaff статус кандидата меняется на "Готов к звонку" (`event_type_47`)
2. Webhook отправляет событие в систему
3. Создается Job `StartTwinCall` в очереди
4. Job получает данные кандидата из Estaff
5. Создается задача на звонок в Twin24
6. Звонок добавляется в очередь Twin24
7. Webhook от Twin24 отслеживает статус звонка

### Отправка WhatsApp сообщения
1. Статус кандидата меняется на "Начать беседу" (`event_type_32`)
2. Создается Job `StartTwinManualConversation`
3. Формируются переменные для шаблона сообщения
4. Сообщение отправляется через Twin24 API
5. `TwinTask` создается для отслеживания статуса
6. При финальном статусе задача удаляется из очереди

## Новый флоу обзвона (`FLOW_MODE=new|hybrid`)

Реализация ТЗ «ПК — обзвон». Режимы (`FLOW_MODE`, по умолчанию `legacy` — поведение выше без изменений):
- `new` — все вебхуки Estaff и TWIN идут через новый флоу, HH-синк выключен.
- `hybrid` — основной поток работает по-старому; новый флоу только для кандидатов, чья вакансия Estaff указана в `FLOW_NEW_VACANCY_IDS`.
  Статусы `new`/`47`/`48` разводятся по `vacancy_id` из вебхука, при его отсутствии `main_vacancy_id` читается из Estaff (джоба `ResolveVacancyAndRoute`).
  Вебхуки TWIN по звонкам разводятся по боту задания. HH-синк работает, но пропускает вакансии нового флоу.

После смены режима: `php artisan config:clear && php artisan queue:restart`. Настройки — `config/flow.php`, решение legacy/new — `App\Services\Flow\FlowRouter`.

Переменные окружения:
```env
FLOW_MODE=hybrid
FLOW_NEW_VACANCY_IDS=7541291626956944847,7509471325786749795   # Estaff-id вакансий нового флоу (для hybrid)
FLOW_TIMEZONE=Europe/Moscow            # бизнес-таймзона для «сегодня» и окон обзвона
ESTAFF_ALLOWED_POSITION_IDS=id1,id2    # разрешённые position_id вакансий (п. 3.1), обязательно
FLOW_OLD_SCRIPT_VACANCY_IDS=7541291626956944847
TWIN_BOT_WARM= / TWIN_BOT_COLD= / TWIN_BOT_OLD= / TWIN_BOT_REMINDER= / TWIN_BOT_FEEDBACK=
```
Таблица «СТАТУСЫ БОТА» (`results.confirmation → event_type_*`) лежит в `config/flow.php` → `confirmation_states`
(источник: https://docs.google.com/spreadsheets/d/1iONRhEtbEhcuvyztLoY8AHxgZO68kxRWjQJWIt6Z1Io). Столбец «Ничего не делать» → `ignored_confirmations`.
При изменении таблицы правится только конфиг.

Сценарий:
1. Estaff webhook `new` / `event_type_47` / `event_type_48` → проверка дублей по телефону и `position_id` (`CandidateGuard`).
   `new` — только журналирование дублей; `48` — дополнительно `location_id` из региона по телефону.
2. Кандидат добавляется в задание TWIN текущего дня нужного типа (тёплый / холодный / старый скрипт для вакансии из `FLOW_OLD_SCRIPT_VACANCY_IDS`),
   одно задание на тип в сутки (`call_tasks`), затем статус `event_type_88`.
3. TWIN webhook `CALL_ENDED` (`/api/twin-webhooks-voice`): не `ANSWERED` или бот старого скрипта → `event_type_35`;
   иначе результат диалога из analyse → `stateCandidate` по таблице статусов, либо для `ПК_Лид` → `eventCandidate` `event_type_49:scheduled`
   (с `user_login`, при ошибке повтор без него) и запись в `interview_schedules`.
4. `app:flow-reminders` (каждые 15 минут, 09:30–17:00) в день собеседования добавляет лидов в задание `ПК_Напоминание DD.MM`,
   если кандидат всё ещё в `event_type_49:scheduled` на эту дату. Ответ `ПК_Напоминание-Время` → `app:flow-feedback` на следующий день
   добавляет кандидата в `ПК_ОС DD.MM`. Результаты `ПК_ОС` не обрабатываются.

Ручной запуск: `php artisan app:flow-reminders --date=2026-07-17`, `php artisan app:flow-feedback --date=2026-07-18`.

## Логирование

Система использует отдельные каналы логирования:
- `app` - общие логи приложения
- `hh` - логи HeadHunter API
- `estaff` - логи Estaff API
- `twin` - логи Twin24 API

Просмотр логов в реальном времени:
```bash
php artisan pail --timeout=0
```

## Очереди

Все фоновые задачи обрабатываются через Laravel Queue:
- Используется database driver (таблица `jobs`)
- Worker настроен на `--tries=1` (без повторных попыток)
- Job'ы сами управляют повторными попытками через delayed dispatch

## Безопасность

⚠️ **Важно**: API endpoints не защищены аутентификацией. Перед production развёртыванием необходимо:
- Добавить `auth:sanctum` middleware на API routes
- Реализовать webhook signature verification
- Включить SSL verification в HTTP клиентах

## Лицензия

Proprietary software. All rights reserved.

---

## Дополнительная документация

- [.github/copilot-instructions.md](.github/copilot-instructions.md) - инструкции для AI ассистентов
- [PINT.md](PINT.md) - руководство по стилю кода

## Поддержка

При возникновении проблем создайте issue или обратитесь к команде разработки.
