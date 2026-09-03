<?php

namespace App\Modules\Communications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $table = 'message_attachments';

    protected $fillable = [
        'message_id',
        'file_name',
        'file_path',
        'public_id',
        'file_type',
        'mime_type',
        'file_size',
        'storage_provider',
        // file_data is written via raw DB statement; not mass-assignable to avoid
        // accidentally sending binary blobs through Eloquent's attribute system.
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // Never include raw binary in JSON serialization / API responses.
    protected $hidden = ['file_data'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function isImage(): bool
    {
        return $this->file_type === 'image' || $this->storage_provider === 'cloudinary';
    }
}
