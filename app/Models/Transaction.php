<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Transaction
 *
 * @property int $id
 * @property int $block_id
 * @property string $hash
 * @property string $sender
 * @property string|null $recipient
 * @property \Illuminate\Support\Carbon $time
 * @property array|null $data
 * @property int $status
 * @property string|null $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $amount
 * @property int $fee
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereBlockId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereRecipient($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereSender($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereUpdatedAt($value)
 * @property string|null $comment
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction byRecipient($recipient)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction bySender($sender)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Transaction byOwner($owner)
 * @property-read \App\Models\User|null $recipientUser
 * @property-read \App\Models\User $senderUser
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Send[] $sends
 * @property-read int|null $sends_count
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction byBlock($blockId)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction emptyMemo()
 * @property string|null $memo
 * @property int $returnable
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereMemo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereReturnable($value)
 */
class Transaction extends Model
{
    protected $table = 'transactions';

    protected $casts = [
        'data' => 'json',
        'recipients' => 'json',
        'amounts' => 'json'
    ];

    public const STATUS_SUCCESS = 0;
    public const STATUS_ERROR = 1;

    protected $dates = [
        'time'
    ];

    public function scopeEmptyMemo($query)
    {
        return $query->where('returnable', 1);
    }

    public function sends()
    {
        return $this->hasMany(Send::class, 'tx_hash', 'hash');
    }
}
