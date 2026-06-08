<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationCenterService
{
    public function queryForUser(User|Supplier $authenticatable): Builder
    {
        if ($authenticatable instanceof Supplier) {
            return DatabaseNotification::query()
                ->where('notifiable_type', Supplier::class)
                ->where('notifiable_id', $authenticatable->id);
        }

        $supplierId = $authenticatable->supplier?->id;

        return DatabaseNotification::query()
            ->where(function (Builder $query) use ($authenticatable, $supplierId) {
                $query->where(function (Builder $userQuery) use ($authenticatable) {
                    $userQuery
                        ->where('notifiable_type', User::class)
                        ->where('notifiable_id', $authenticatable->id);
                });

                if ($supplierId) {
                    $query->orWhere(function (Builder $supplierQuery) use ($supplierId) {
                        $supplierQuery
                            ->where('notifiable_type', Supplier::class)
                            ->where('notifiable_id', $supplierId);
                    });
                }
            });
    }

    public function unreadCountForUser(User|Supplier $authenticatable): int
    {
        return (int) $this->queryForUser($authenticatable)
            ->whereNull('read_at')
            ->count();
    }

    public function recentForUser(User|Supplier $authenticatable, int $limit = 5): Collection
    {
        return $this->queryForUser($authenticatable)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function findForUser(User|Supplier $authenticatable, string $notificationId): ?DatabaseNotification
    {
        return $this->queryForUser($authenticatable)
            ->where('id', $notificationId)
            ->first();
    }

    public function markAllAsReadForUser(User|Supplier $authenticatable): void
    {
        $this->queryForUser($authenticatable)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
