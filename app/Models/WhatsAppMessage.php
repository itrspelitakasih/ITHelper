<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'message_id',
        'device_id',
        'session_id',
        'phone',
        'name',
        'message',
        'direction',
        'status',
        'error_message',
    ];

    /**
     * Check if message is inbound.
     */
    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Check if message is outbound.
     */
    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }
}
