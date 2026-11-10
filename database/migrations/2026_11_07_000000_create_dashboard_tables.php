<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('colour', 7);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // SQLite permits forward foreign-key references, avoiding a table rebuild
        // for the circular project/next-action relationship.
        DB::unprepared(<<<'SQL'
            CREATE TABLE projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                area_id INTEGER NOT NULL REFERENCES areas(id) ON DELETE RESTRICT,
                title VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'paused' CHECK (status IN ('active', 'paused', 'done')),
                next_action_task_id INTEGER REFERENCES tasks(id) ON DELETE RESTRICT,
                notes TEXT,
                target_date DATE,
                created_at DATETIME,
                updated_at DATETIME,
                CHECK (status != 'active' OR next_action_task_id IS NOT NULL)
            );
            CREATE INDEX projects_area_status_index ON projects(area_id, status);
            CREATE INDEX projects_target_date_index ON projects(target_date);
            CREATE INDEX projects_next_action_task_id_index ON projects(next_action_task_id);

            CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER REFERENCES projects(id) ON DELETE RESTRICT,
                title VARCHAR(255) NOT NULL,
                tier VARCHAR(20) NOT NULL DEFAULT 'should' CHECK (tier IN ('must', 'should', 'could')),
                due_date DATE,
                scheduled_for DATE,
                estimate_minutes INTEGER CHECK (estimate_minutes IS NULL OR estimate_minutes > 0),
                status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'done', 'dropped')),
                completed_at DATETIME,
                carry_count INTEGER NOT NULL DEFAULT 0 CHECK (carry_count >= 0),
                created_at DATETIME,
                updated_at DATETIME,
                CHECK ((status = 'done' AND completed_at IS NOT NULL)
                    OR (status != 'done' AND completed_at IS NULL))
            );
            CREATE INDEX tasks_project_id_index ON tasks(project_id);
            CREATE INDEX tasks_scheduled_status_index ON tasks(scheduled_for, status);
            CREATE INDEX tasks_due_date_index ON tasks(due_date);

            CREATE TRIGGER projects_next_action_insert BEFORE INSERT ON projects
            WHEN NEW.next_action_task_id IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM tasks WHERE id = NEW.next_action_task_id AND project_id = NEW.id
                    AND (NEW.status != 'active' OR status = 'pending')
            )
            BEGIN SELECT RAISE(ABORT, 'Next action must belong to the project and be pending when active.'); END;

            CREATE TRIGGER projects_next_action_update BEFORE UPDATE ON projects
            WHEN NEW.next_action_task_id IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM tasks WHERE id = NEW.next_action_task_id AND project_id = NEW.id
                    AND (NEW.status != 'active' OR status = 'pending')
            )
            BEGIN SELECT RAISE(ABORT, 'Next action must belong to the project and be pending when active.'); END;

            CREATE TRIGGER tasks_protect_next_action BEFORE UPDATE OF status, project_id ON tasks
            WHEN EXISTS (
                SELECT 1 FROM projects WHERE next_action_task_id = OLD.id
                    AND (NEW.project_id IS NULL OR NEW.project_id != projects.id
                        OR (projects.status = 'active' AND NEW.status != 'pending'))
            )
            BEGIN SELECT RAISE(ABORT, 'Replace the next action or pause/finish its project first.'); END;

            CREATE TABLE dashboard_settings (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                default_focus_minutes INTEGER NOT NULL DEFAULT 240 CHECK (default_focus_minutes >= 0),
                created_at DATETIME,
                updated_at DATETIME
            );
            CREATE TABLE daily_capacities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date DATE NOT NULL UNIQUE,
                focus_minutes INTEGER NOT NULL CHECK (focus_minutes >= 0),
                created_at DATETIME,
                updated_at DATETIME
            );
            CREATE TRIGGER users_single_account BEFORE INSERT ON users
            WHEN EXISTS (SELECT 1 FROM users)
            BEGIN SELECT RAISE(ABORT, 'Only one dashboard account is allowed.'); END;
            CREATE TRIGGER users_owner_id_insert AFTER INSERT ON users
            WHEN NEW.id != 1
            BEGIN SELECT RAISE(ABORT, 'The dashboard account must have ID 1.'); END;
            CREATE TRIGGER users_owner_id_update BEFORE UPDATE OF id ON users
            WHEN NEW.id != 1
            BEGIN SELECT RAISE(ABORT, 'The dashboard account must have ID 1.'); END;
            SQL);

        Schema::create('commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->json('days_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('fixed_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->date('date')->index();
            $table->timestamps();
        });

        Schema::create('waiting_ons', function (Blueprint $table) {
            $table->id();
            $table->text('description');
            $table->string('who');
            $table->date('since_date')->index();
            $table->date('chase_on')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('inbox_items', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->timestamp('triaged_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('daily_reviews', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->text('wins')->nullable();
            $table->json('carried_over')->default('[]');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS users_single_account;
            DROP TRIGGER IF EXISTS users_owner_id_insert;
            DROP TRIGGER IF EXISTS users_owner_id_update;
            DROP TRIGGER IF EXISTS projects_next_action_insert;
            DROP TRIGGER IF EXISTS projects_next_action_update;
            DROP TRIGGER IF EXISTS tasks_protect_next_action;');

        foreach (['daily_reviews', 'inbox_items', 'waiting_ons', 'fixed_dates', 'commitments',
            'daily_capacities', 'dashboard_settings'] as $table) {
            Schema::dropIfExists($table);
        }

        // Remove circular references before dropping tables with existing records.
        DB::table('projects')->update(['status' => 'paused', 'next_action_task_id' => null]);
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('areas');
    }
};
