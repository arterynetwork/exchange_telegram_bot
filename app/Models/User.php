<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Telegram\Bot\Objects\Update;

/**
 * App\Models\User
 *
 * @property int $id
 * @property int $chat_id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $in_address
 * @property string|null $out_address
 * @property int $balance
 * @property int $locked
 * @property int $offer_active
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read int|null $notifications_count
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereChatId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereInAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereOfferActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereOutAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string $bot_state
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBotState($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PaymentMethod[] $paymentMethods
 * @property-read int|null $payment_methods_count
 * @property string $token_price
 * @method static \Illuminate\Database\Eloquent\Builder|User whereTokenPrice($value)
 * @property int $buy_offer
 * @property int $buy_min
 * @property int $buy_total
 * @property int $buy_price
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBuyMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBuyOffer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBuyPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBuyTotal($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PaymentMethod[] $buyMethods
 * @property-read int|null $buy_methods_count
 * @property string|null $buy_address
 * @property string|null $buy_address_card
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBuyAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBuyAddressCard($value)
 * @property int $banned
 * @property string|null $reason
 * @property string $lang
 * @property float $sell_p
 * @property float $buy_p
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBanned($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBuyP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSellP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereBuyExpire($value)
 * @property \Illuminate\Support\Carbon|null $buy_expire
 * @property string|null $comment
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const BOT_STATE_NORMAL = 'NORMAL';
    public const BOT_STATE_WITHDRAW_AMOUNT = 'WITHDRAW_AMOUNT';
    public const BOT_STATE_WITHDRAW_WALLET = 'WITHDRAW_WALLET';
    public const BOT_STATE_WITHDRAW_CONFIRM = 'WITHDRAW_CONFIRM';
    public const BOT_STATE_SELL_METHOD_SETTINGS = 'SELL_METHOD_SETTINGS';
    public const BOT_STATE_BUY_AMOUNT = 'BUY_AMOUNT';
    public const BOT_STATE_BUY_WALLET = 'BUY_WALLET';
    public const BOT_STATE_BUY_METHOD = 'BUY_METHOD';
    public const BOT_STATE_CONFIRMATION_IMAGE = 'CONFIRMATION_IMAGE';
    public const BOT_STATE_CHANGE_PRICE = 'CHANGE_PRICE';

    public const BOT_STATE_BUY_SETTINGS_MIN = 'SELL_SETTINGS_MIN';
    public const BOT_STATE_BUY_SETTINGS_TOTAL = 'SELL_SETTINGS_TOTAL';
    public const BOT_STATE_BUY_SETTINGS_PRICE = 'SELL_SETTINGS_PRICE';
    public const BOT_STATE_BUY_SETTINGS_ADDRESS = 'BUY_SETTINGS_ADDRESS';

    public const BOT_STATE_SELL_ADS_AMOUNT = 'SELL_ADS_AMOUNT';
    public const BOT_STATE_SELL_ADS_PRICE = 'SELL_ADS_PRICE';
    public const BOT_STATE_SELL_ADS_CONFIRM = 'SELL_ADS_CONFIRM';

    public const CHAT_ID_MAGIC = 20787493023;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $dates = [
        'buy_expire'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * @param $chatId
     * @param string $lang
     * @return User|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    public static function getUser($chatId, $lang = 'ru')
    {
        $user = User::where('chat_id', $chatId)->first();
        if ($user) {
            if (!$user->name) {
                $chat = \Telegram::bot()->getChat(['chat_id' => $chatId]);
                if ($chat) {
                    \Log::debug(print_r($chat, 1));
                    $user->name = $chat->first_name;
                    $user->save();
                }
            }
            return $user;
        }

        $user = new User();
        $user->chat_id = $chatId;
        $user->lang = $lang;
        $chat = \Telegram::bot()->getChat(['chat_id' => $chatId]);
        if ($chat) {
            $user->name = $chat->first_name;
        }
        $user->save();

        return $user;
    }

    public static function getUserByUpdate(Update $update)
    {
        return self::getUser($update->getChat()->id);
    }

    public function paymentMethods()
    {
        return $this->belongsToMany(PaymentMethod::class)
            ->withPivot('info');
    }

    public function buyMethods()
    {
        return $this->belongsToMany(PaymentMethod::class,
            'payment_user',
            'user_id',
            'payment_method_id');
    }

    public static function invertCode($code)
    {
        return $code ^ self::CHAT_ID_MAGIC;
    }
}
