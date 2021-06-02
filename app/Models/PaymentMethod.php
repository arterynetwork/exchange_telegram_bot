<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

/**
 * App\Models\PaymentMethod
 *
 * @property int $id
 * @property string $name
 * @property string|null $params
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod query()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod whereParams($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $name_ru
 * @property string|null $name_en
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod whereNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentMethod whereNameRu($value)
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'payment_methods';


    public function getNameAttribute()
    {
        $name = $this->{"name_" . App::getLocale()};
        if (!$name) {
            $name = $this->{"name_ru"};
        }

        return $name;
    }
}
