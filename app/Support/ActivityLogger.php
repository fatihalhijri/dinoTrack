<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class ActivityLogger
{
    private const string HIDDEN_VALUE = '***';

    /**
     * @param  User|null  $by  null berarti dilakukan sistem (scheduler, webhook, job).
     * @param  array<string, mixed>  $properties
     */
    public function log(string $action, ?Model $subject = null, ?User $by = null, array $properties = []): ActivityLog
    {
        $log = new ActivityLog([
            'action' => $action,
            'properties' => $properties === [] ? null : $properties,
        ]);

        $log->user()->associate($by);

        if ($subject !== null) {
            $log->subject()->associate($subject);
        }

        $log->save();

        return $log;
    }

    /**
     * Perubahan yang belum disimpan dalam bentuk [kolom => [lama, baru]]. Panggil sebelum save().
     * Nilai kolom tersembunyi (misalnya password router) disamarkan agar tidak masuk log.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function pendingChanges(Model $model): array
    {
        $hidden = $model->getHidden();
        $changes = [];

        foreach (array_keys($model->getDirty()) as $column) {
            $changes[$column] = in_array($column, $hidden, true)
                ? [self::HIDDEN_VALUE, self::HIDDEN_VALUE]
                : [$model->getRawOriginal($column), $model->getAttributes()[$column]];
        }

        return $changes;
    }
}
