<?php

namespace Liamdemafelix\LaravelAuditor;

use Carbon\Laravel\ServiceProvider;
use Illuminate\Support\Facades\DB;

class AuditorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([__DIR__ . "/config/auditor.php" => config_path("auditor.php")], "config");
        $this->publishes([__DIR__ . "/migrations/" => database_path("migrations")], "migrations");

        // Loop through defined models and start observing for changes
        foreach (config("auditor.models") as $model) {
            if (class_exists($model)) {
                // "onCreate" watcher
                if (config("auditor.watchers.create")) {
                    $model::created(function ($record) use ($model) {
                        $this->registerCreate($record, $model);
                    });
                }

                // "onUpdate" watcher
                if (config("auditor.watchers.update")) {
                    $model::updated(function ($record) use ($model) {
                        $this->registerUpdate($record, $model);
                    });
                }

                // "onDelete" watcher
                if (config("auditor.watchers.delete")) {
                    $model::deleted(function ($record) use ($model) {
                        $this->registerDelete($record, $model);
                    });
                }
            }
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . "/config/auditor.php", "auditor");
    }

    protected function strip($model, $data) : array
    {
        $instance = new $model();
        $data = (array) $data;

        // Define discards
        $globalDiscards = (!empty(config("auditor.discards"))) ? config("auditor.discards") : [];
        $modelDiscards = (!empty($instance->discarded)) ? $instance->discarded : [];

        // Start discards
        foreach ($data as $key => $value) {
            if (in_array($key, $modelDiscards) || (!empty($globalDiscards) && in_array($key, $globalDiscards))) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function generate($action, $old = [], $new = []): bool|string
    {
        $data = [];

        switch ($action) {
            default:
                throw new \InvalidArgumentException("Unknown action `{$action}`.");

            case "create":
                if (config("auditor.watchers.create")) {
                    if (empty($new)) {
                        throw new \ArgumentCountError("Action `create` expects new data.");
                    }

                    foreach ($new as $key => $value) {
                        $data[$key] = [
                            "old" => null,
                            "new" => $value
                        ];
                    }
                }
                break;

            case "update":
                if (empty($new)) {
                    // We're restoring a soft-deleted entry. This gets captured here.
                    if (config("auditor.watchers.restore")) {
                        foreach ($old as $key => $value) {
                            $data[$key] = [
                                "old" => $old[$key],
                                "new" => config("auditor.messages.restore_softdeleted", "Data restored.")
                            ];
                        }
                    }
                } else {
                    // An actual updated entry
                    if (config("auditor.watchers.update")) {
                        if (empty($old) || empty($new)) {
                            throw new \ArgumentCountError("Action `update` expects both old and new data.");
                        }
                        foreach ($new as $key => $value) {
                            $data[$key] = [
                                "old" => $old[$key],
                                "new" => $value
                            ];
                        }
                    }
                }
                break;

            case "delete":
                if (config("auditor.watchers.delete")) {
                    if (empty($old)) {
                        throw new \ArgumentCountError("Action `delete` expects new data.");
                    }
                    foreach ($old as $key => $value) {
                        $data[$key] = [
                            "old" => $value,
                            "new" => null
                        ];
                    }
                }
                break;
        }

        return json_encode($data);
    }

    public function getUserId()
    {
        return auth()->guest() ? null : auth()->user()->getAuthIdentifier();
    }

    protected function registerCreate($record, $model)
    {
        $data = $this->generate("create", [], $this->strip($model, $record->getAttributes()));

        DB::table("audit_logs")->insert([
            "user_id" => $this->getUserId(),
            "model_name" => $model,
            "model_id" => $record->{$record->getKeyName()},
            "action" => "create",
            "record" => $data,
            "created_at" => now(),
            "updated_at" => now()
        ]);
    }

    protected function registerUpdate($record, $model)
    {
        $data = $this->generate("update", $this->strip($model, $record->getOriginal()), $this->strip($model, $record->getChanges()));

        DB::table("audit_logs")->insert([
            "user_id" => $this->getUserId(),
            "model_name" => $model,
            "model_id" => $record->{$record->getKeyName()},
            "action" => "update",
            "record" => $data,
            "created_at" => now(),
            "updated_at" => now()
        ]);
    }

    protected function registerDelete($record, $model)
    {
        $data = $this->generate("delete", $this->strip($model, $record->getAttributes()));

        DB::table("audit_logs")->insert([
            "user_id" => $this->getUserId(),
            "model_name" => $model,
            "model_id" => $record->{$record->getKeyName()},
            "action" => "delete",
            "record" => $data,
            "created_at" => now(),
            "updated_at" => now()
        ]);
    }
}
