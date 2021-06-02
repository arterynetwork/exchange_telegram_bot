<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\BannedProps
 *
 * @property int $id
 * @property string $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|BannedProps newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BannedProps newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BannedProps query()
 * @method static \Illuminate\Database\Eloquent\Builder|BannedProps whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannedProps whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannedProps whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannedProps whereValue($value)
 * @mixin \Eloquent
 */
class BannedProps extends Model
{
    protected $table = 'banned_props';
}
