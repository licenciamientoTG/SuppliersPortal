<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmployeePositionBudgetProfile extends Model
{
    use HasFactory;

    protected $table = 'employee_position_budget_profile';

    protected $fillable = [
        'raw_job_title',
        'normalized_job_title',
        'budget_profile_id',
        'is_excluded',
        'employees_count',
    ];

    protected $casts = [
        'is_excluded' => 'boolean',
        'employees_count' => 'integer',
    ];

    public function budgetProfile(): BelongsTo
    {
        return $this->belongsTo(BudgetProfile::class);
    }

    public static function normalizeJobTitle(?string $jobTitle): ?string
    {
        if ($jobTitle === null || trim($jobTitle) === '') {
            return null;
        }

        return Str::of($jobTitle)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
