<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Withdraw
 *
 * @property int $id
 * @property int $chat_id
 * @property string $account_card
 * @property string $account_address
 * @property int $amount
 * @property string|null $txhash
 * @property string|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw query()
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereAccountAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereAccountCard($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereChatId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereTxhash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int $status
 * @property int $fee
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Withdraw whereStatus($value)
 */
class Withdraw extends Model
{
    use HasFactory;

    protected $table = "withdrawals";
}
