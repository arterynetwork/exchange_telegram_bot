<?php


namespace App\Bot\Workflows;


use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update as UpdateObject;

class BaseWorkflow
{
    /**
     * @var \Telegram\Bot\Api
     */
    protected $bot;

    public function __construct()
    {
        $this->bot = Telegram::bot();
    }

    public function sendText(UpdateObject $update, $text, $params = [])
    {
        try {
            $this->bot->sendMessage(array_merge([
                'chat_id' => $update->getChat()->id,
                'text' => $text
            ], $params));
        } catch (\Throwable $ex) {
            \Log::error($ex);
        }
    }

    public function sendMessage($chatId, $text, $params = [])
    {
        try {
            $this->bot->sendMessage(array_merge([
                'chat_id' => $chatId,
                'text' => $text
            ], $params));
        } catch (\Throwable $ex) {
            \Log::error($ex);
        }
    }

    public function setState(UpdateObject $update, $state)
    {
        $user = User::getUser($update->getChat()->id);
        $user->bot_state = $state;
        $user->save();
    }

    public function saveToState($key, $value)
    {
        return Cache::set($key, $value);
    }

    public function getFromState($key, $default = null)
    {
        return Cache::get($key, $default);
    }

    /**
     * @param User $user
     * @param UpdateObject $update
     * @return mixed
     */
    public function processState(User $user, UpdateObject $update)
    {
        return false;
    }

    public function processPhoto(User $user, UpdateObject $update)
    {
        return false;
    }

    public function processCallbackQuery($query, UpdateObject $update)
    {
        return false;
    }

    /**
     * @param $keyboard
     * @return Keyboard
     */
    public function getInlineKeyboardReply($keyboard)
    {
        return new Keyboard([
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'inline_keyboard' => $keyboard
        ]);
    }

    public function inputAmount(UpdateObject $update, $min = 0, $max = PHP_INT_MAX, $digits = 2)
    {
        $m = min($min, $max);
        $ma = max($min, $max);

        $min = $m;
        $max = $ma;

        $text = str_replace(',', '.', trim($update->getMessage()->text));
        if (!is_numeric($text)) {
            $this->sendText($update, __('bot.validation.not_number'));
            return false;
        } else {
            $amount = floatval($text);

            if ($amount < $min) {
                $this->sendText($update, __('bot.validation.not_less') . $min);
                return false;
            }

            if ($max < PHP_INT_MAX) {
                if ($amount > $max) {
                    $this->sendText($update, __('bot.validation.not_more') . $max);
                    return false;
                }
            }

            $priceArray = explode('.', $amount . '');
            if (isset($priceArray[1]) && mb_strlen($priceArray[1]) > $digits) {
                $this->sendText($update, __('bot.validation.no_more_signs', ['digits' => $digits]));
                return true;
            }

            return $amount;
        }
    }
}
