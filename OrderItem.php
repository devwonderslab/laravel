<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrderItem extends Model
{
    // Using same numbers as in Order model for compatibility reasons. May be changed if needed.
    const STATUS_CANCELLED = 0;

    const STATUS_SERVED = 3;
    const STATUS_PREPARING = 4;
    const STATUS_PENDING = 5;

    public static function getStatusTexts()
    {
        return [
            self::STATUS_CANCELLED => trans('messages.statusCancelled'),

            self::STATUS_SERVED => trans('messages.statusServed'),
            self::STATUS_PREPARING => trans('messages.statusPreparing'),
            self::STATUS_PENDING => trans('messages.statusPending'),
        ];
    }

    public static function getStatusKey($status)
    {
        $keys = [
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_SERVED => 'Served',
            self::STATUS_PREPARING => 'Preparing',
            self::STATUS_PENDING => 'Pending',
        ];
        return (isset($keys[$status])) ? $keys[$status] : $status;
    }

    public function order()
    {
        return $this->belongsTo('App\Models\Order');
    }

    public function item()
    {
        return $this->belongsTo('App\Models\Item');
    }

    public function getStatusText()
    {
        return (isset(self::getStatusTexts()[$this->status])) ? self::getStatusTexts()[$this->status] : $this->status;
    }

    public function translations()
    {
        return $this->hasMany('App\Models\ItemTranslation', 'item_id', 'item_id');
    }
}
