<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $channel
 * @property string $priority
 * @property string $status
 * @property string $recipient_id
 * @property string $content
 * @property string $idempotency_key
 * @property string|null $gateway_id
 * @property string|null $error_message
 * @property int $retry_count
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NotificationModel extends Model
{
    protected $table = 'notifications_log';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'id',
        'channel',
        'priority',
        'status',
        'recipient_id',
        'content',
        'idempotency_key',
        'gateway_id',
        'error_message',
        'retry_count',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'retry_count' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
