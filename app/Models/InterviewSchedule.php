<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead (ПК_Лид) waiting for the reminder call on the interview day and the
 * feedback call the day after (ТЗ раздел 6).
 *
 * @property int $id
 * @property int $candidate_id
 * @property int|null $vacancy_id
 * @property string $phone
 * @property \Carbon\Carbon $interview_date
 * @property \Carbon\Carbon|null $interview_at
 * @property string|null $customer_login
 * @property string $stage
 * @property string|null $skip_reason
 * @property string|null $reminder_autocall_id
 * @property \Carbon\Carbon|null $reminder_sent_at
 * @property \Carbon\Carbon|null $feedback_date
 * @property string|null $feedback_autocall_id
 * @property \Carbon\Carbon|null $feedback_sent_at
 * @property array|null $results
 * @property string|null $comment
 */
class InterviewSchedule extends Model
{
    public const STAGE_SCHEDULED = 'scheduled';

    public const STAGE_REMINDER_SENT = 'reminder_sent';

    public const STAGE_REMINDER_SKIPPED = 'reminder_skipped';

    public const STAGE_FEEDBACK_PENDING = 'feedback_pending';

    public const STAGE_FEEDBACK_SENT = 'feedback_sent';

    public const STAGE_CANCELLED = 'cancelled';

    public const STAGE_SUPERSEDED = 'superseded';

    /** Stages in which the row still waits for an action from this service. */
    public const ACTIVE_STAGES = [
        self::STAGE_SCHEDULED,
        self::STAGE_REMINDER_SENT,
        self::STAGE_FEEDBACK_PENDING,
    ];

    protected $table = 'interview_schedules';

    protected $fillable = [
        'candidate_id',
        'vacancy_id',
        'phone',
        'interview_date',
        'interview_at',
        'customer_login',
        'stage',
        'skip_reason',
        'reminder_autocall_id',
        'reminder_sent_at',
        'feedback_date',
        'feedback_autocall_id',
        'feedback_sent_at',
        'results',
        'comment',
    ];

    protected $casts = [
        'candidate_id' => 'integer',
        'vacancy_id' => 'integer',
        'interview_date' => 'date:Y-m-d',
        'interview_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'feedback_date' => 'date:Y-m-d',
        'feedback_sent_at' => 'datetime',
        'results' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('stage', self::ACTIVE_STAGES);
    }

    public function scopeDueForReminder(Builder $query, string $date): Builder
    {
        return $query->whereDate('interview_date', $date)->where('stage', self::STAGE_SCHEDULED);
    }

    public function scopeDueForFeedback(Builder $query, string $date): Builder
    {
        return $query->whereDate('feedback_date', $date)->where('stage', self::STAGE_FEEDBACK_PENDING);
    }
}
