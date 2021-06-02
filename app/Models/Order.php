<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Order
 *
 * @property int $id
 * @property int $chat_id
 * @property int $buyer_id
 * @property int $payment_method_id
 * @property int $amount
 * @property int $amount_currency
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereAmountCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereBuyerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereChatId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order wherePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $address
 * @property string|null $card_number
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereCardNumber($value)
 * @property string|null $txhash
 * @property int $tx_status
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereTxStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereTxhash($value)
 * @property string|null $screenshot
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereScreenshot($value)
 * @property string $doctype
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereDoctype($value)
 * @property int $is_sell
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereIsSell($value)
 * @property string|null $info
 * @property-read \App\Models\User $buyer
 * @property-read \App\Models\User $seller
 * @method static \Illuminate\Database\Eloquent\Builder|Order byBuyer($id)
 * @method static \Illuminate\Database\Eloquent\Builder|Order bySeller($id)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereInfo($value)
 * @property string|null $remind_at
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereRemindAt($value)
 * @property string|null $status_changed_at
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereStatusChangedAt($value)
 * @property string $amount_byr
 * @property string $amount_uah
 * @property string $amount_kzt
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereAmountByr($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereAmountKzt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereAmountUah($value)
 * @property string $amount_usd
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereAmountUsd($value)
 */
class Order extends Model
{
    use HasFactory;

    public const STATUS_NEW = 0;
    public const STATUS_IN_PROCESS = 1;
    public const STATUS_EXPIRE = 2;
    public const STATUS_PAYMENT_SEND = 3;
    public const STATUS_COMPLETE = 4;
    public const STATUS_CANCELED_BY_SELLER = 5;
    public const STATUS_CANCELED_BY_BUYER = 6;
    public const STATUS_COMPLETE_BY_BOT = 7;
    public const STATUS_BUYER_WAIT = 8;
    public const STATUS_CANCELED_BY_BOT = 9;

    public const STATUS_NAMES = [
        self::STATUS_NEW => 'bot.order_status.STATUS_NEW',
        self::STATUS_IN_PROCESS => 'bot.order_status.STATUS_IN_PROCESS',
        self::STATUS_EXPIRE => 'bot.order_status.STATUS_EXPIRE',
        self::STATUS_PAYMENT_SEND => 'bot.order_status.STATUS_PAYMENT_SEND',
        self::STATUS_COMPLETE => 'bot.order_status.STATUS_COMPLETE',
        self::STATUS_CANCELED_BY_SELLER => 'bot.order_status.STATUS_CANCELED_BY_SELLER',
        self::STATUS_CANCELED_BY_BUYER => 'bot.order_status.STATUS_CANCELED_BY_BUYER',
        self::STATUS_COMPLETE_BY_BOT => 'bot.order_status.STATUS_COMPLETE_BY_BOT',
        self::STATUS_BUYER_WAIT => 'bot.order_status.STATUS_BUYER_WAIT',
        self::STATUS_CANCELED_BY_BOT => 'bot.order_status.STATUS_CANCELED_BY_BOT',
    ];

    public const TX_STATUS_NEW = 0;
    public const TX_STATUS_SEND = 1;
    public const TX_STATUS_CONFIRMED = 2;
    public const TX_STATUS_ERRORED = 3;

    protected $dates = ['status_changed_at'];

    public const MINUTES_TO_PAY = 30;

    protected $table = 'orders';

    public function seller()
    {
        return $this->belongsTo(User::class, 'chat_id', 'chat_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id', 'chat_id');
    }

    public function scopeBySeller($query, $id)
    {
        $query->where('chat_id', $id);
    }

    public function scopeByBuyer($query, $id)
    {
        $query->where('buyer_id', $id);
    }

    public function setStatusAttribute($value)
    {
        if (!isset($this->attributes['status']) || $this->attributes['status'] != $value) {
            $this->status_changed_at = now();
        }
        $this->attributes['status'] = $value;
    }
}
