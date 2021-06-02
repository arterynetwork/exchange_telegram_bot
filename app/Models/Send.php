<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Send
 *
 * @property int $id
 * @property int|null $withdraw_id
 * @property int|null $order_id
 * @property string $address
 * @property int $amount
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Send newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Send newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Send query()
 * @method static \Illuminate\Database\Eloquent\Builder|Send whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Send whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Send whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Send whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Send whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Send whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Send whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Send whereWithdrawId($value)
 * @mixin \Eloquent
 * @property string|null $tx_hash
 * @method static \Illuminate\Database\Eloquent\Builder|Send whereTxHash($value)
 */
class Send extends Model
{
    use HasFactory;

    protected $table = 'sends';
}
