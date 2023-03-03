<?php

namespace Liamdemafelix\LaravelAuditor;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $primaryKey = "id";
    protected $fillable = [
        "user_id",
        "model_name",
        "model_id",
        "action",
        "record"
    ];
}
