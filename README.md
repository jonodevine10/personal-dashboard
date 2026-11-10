# Personal Dashboard

A single-user productivity dashboard built with Laravel 13, Filament 5 and SQLite.

## Current stage

Stages 1 and 2 are implemented: schema, validated models, editable seed
placeholders, single-user authentication, the Today page, focus capacity,
recurring commitments, persistent quick capture and transactional Inbox triage.
Areas/Projects, Week, Waiting On, Fixed Dates and Evening Shutdown remain future
stages.

## Requirements

- PHP 8.3+ with PDO SQLite and the extensions required by Composer.
- Composer 2.
- Node 22.12+ (or a newer supported release) and npm for Vite assets.

## Set up

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan dashboard:user
npm ci
npm run build
php artisan serve
```

Open http://localhost:8000/admin and sign in with the account you provisioned.
For the already-installed working copy, run `php artisan dashboard:user`, then
`php artisan serve`. No account is created by seeding; registration is disabled.
The account command asks for a confirmed password without exposing it in shell
history. The database allows only one account; panel access is restricted to ID 1.

Set `DASHBOARD_TIMEZONE` in `.env` to an IANA timezone such as `Europe/London`
before using calendar screens. Application timestamps stay in UTC; local dates
and commitment times use the dashboard timezone. Do not commit `.env`, database
files or credentials. For deployment, use HTTPS and set `APP_ENV=production`,
`APP_DEBUG=false` and `APP_URL` to the actual address.

Filament uses locally published assets. `composer install` republishes these via
the generated `filament:upgrade` hook; run `php artisan filament:assets` if they
need rebuilding independently. Use `npm run dev` alongside `php artisan serve`
when working on Vite-managed frontend assets.

## Seed data

`php artisan db:seed` is repeatable and preserves edits.

- Areas: TheGallery Work, Dissertation, Training, M&S, Side Builds, Life Admin,
  Parking Lot.
- Two disabled recurring commitment templates: early shifts (04:00-08:00) and
  work days (09:00-17:00 placeholder). Each record can cover four chosen
  weekdays. Configure the weekdays and actual work hours before enabling.
- Singleton focus budget: 240 minutes/day; dated overrides accept zero.
- No sample tasks, projects or account credentials.

Commitments are managed under **Commitments** in the panel. Edit the disabled
early-shift and work-day templates, select the weekdays they start on, and enable
them when their actual schedule is configured. A commitment can cross midnight;
Today displays its overlap with the current local date.

Tasks and projects do not yet have general management screens. Inbox triage can
create a task (unscheduled unless you pick a date), append a timestamped note to
an existing project, or create a paused Parking Lot project. Today’s **Add task**
action schedules directly created tasks for today by default. Inbox capture stays
in the panel header on desktop and mobile.

Today groups pending scheduled tasks by Must, Should and Could. Completing an
active project's linked next action requires entering a replacement action or
choosing to pause/finish that project. Rescheduling later increments the task's
visible carry counter. Focus capacity totals known estimates for pending scheduled
tasks only. Tasks without estimates are flagged as unknown rather than counted as
zero, and scheduled fixed commitments display separately from focus capacity.
Edit the dashboard-wide default or today’s override directly on the Today page;
**Reset** removes the daily override and returns to the current default.

## Domain rules

Projects use `next_action_task_id`, not duplicated next-action text. An active
project must reference its own pending task. Use `App\Actions\CreateProject` to
create a project and its next task transactionally, `TransitionTask` to complete,
drop or reopen tasks, and `RescheduleTask` for date moves. Completing/dropping an
active next action requires a replacement pending task or pausing/finishing the
project. Models validate ordinary saves too; SQLite checks, foreign keys and
triggers protect linked actions even from bulk writes.

Calendar dates are stored as `YYYY-MM-DD`, timestamps as UTC, and commitment
times as `HH:MM`. Overnight commitments are permitted. Pending tasks moved from
an existing scheduled date to a later date increment `carry_count` once.
Initial scheduling, same/earlier dates, completion and dropping do not.
Done tasks get `completed_at`; reopening/dropping clears it. Raw bulk updates
bypass model behavior and should not be used for normal domain mutations.

SQLite-specific SQL is intentional: it supplies check constraints and circular
relationship protection without rebuilding tables. Transactions use IMMEDIATE
mode and a five-second busy timeout to serialize writes from multiple tabs.
Referenced areas/projects/next-action tasks cannot be deleted; favor lifecycle
statuses instead.

## Verify

```sh
php artisan test
vendor/bin/pint --test
npm run build
```

Tests use an isolated in-memory SQLite database, never your local data. They
cover seed preservation, record casts, capacity calculations and overrides,
linked next-action rules and atomic transitions, carry counts, commitment
occurrences including overnight shifts, Inbox capture and triage transactions,
migration rollback with populated relationships, provisioning, login and panel
access.

## Build stages

1. Foundation (complete).
2. Today, quick capture and Inbox triage (complete).
3. Areas/Projects and next-action interactions.
4. Week view.
5. Waiting On and Fixed Dates widgets.
6. Evening Shutdown wizard.
7. Mobile/dark-mode polish and final feature verification.
