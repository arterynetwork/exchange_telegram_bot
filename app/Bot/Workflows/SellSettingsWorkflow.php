<?php


namespace App\Bot\Workflows;


use App\Models\BannedProps;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update as UpdateObject;

class SellSettingsWorkflow extends BaseWorkflow
{

    function processState(User $user, UpdateObject $update)
    {
        $text = $update->getMessage()->text;

        switch ($user->bot_state) {
            case User::BOT_STATE_SELL_METHOD_SETTINGS:
                $pm = $this->getFromState($user->chat_id . '_selected_method');

                if ($pm) {
                    // Отключаем метод оплаты
                    if (trim($text)) {
                        $user->paymentMethods()->syncWithoutDetaching([
                            $pm->id => ['info' => trim($text)]
                        ]);
                        $this->bot->triggerCommand('sell_settings', $update);
                        $this->sendText($update, __('bot.buy_settings.saved'));
                        $user->bot_state = User::BOT_STATE_NORMAL;

                        // Проверяем, не надо ли забанить юзера за рекивизиты
                        try {
                            $props = BannedProps::pluck('value');
                            $checkText = preg_replace('/[^0-9]/', '', $text);

                            foreach ($props as $prop) {
                                if ($prop != '' && (mb_strpos($checkText, mb_strtolower($prop)) !== false)) {
                                    $user->comment .= "\n Забанен из-за совпадения реквизитов " . $prop;
                                    $user->banned = true;
                                }
                            }
                        } catch (\Throwable $ex) {
                            \Log::error($ex);
                        }

                        $user->save();
                    }
                }
                return true;

            case User::BOT_STATE_CHANGE_PRICE:
                $price = floatval(str_replace(',', '.', trim($text)));
                $priceArray = explode('.', $price . '');
                if (isset($priceArray[1]) && mb_strlen($priceArray[1]) > 2) {
                    $this->sendText($update, __('bot.validation.no_more_signs', ['digits' => 2]));
                    return true;
                }

                if ($price < config('artr.min_percent')) {
                    $this->sendText($update, __('bot.sell_settings.price_bounds', [
                        'from' => config('artr.min_percent'),
                        'to' => config('artr.max_percent'),
                    ]));
                    return true;
                }

                if ($price > config('artr.max_percent')) {
                    $this->sendText($update, __('bot.sell_settings.price_bounds', [
                        'from' => config('artr.min_percent'),
                        'to' => config('artr.max_percent'),
                    ]));
                    return true;
                }

                $user->sell_p = $price;
                $user->token_price = round(config('artr.price') * (1 + $price / 100), 2);
                $user->bot_state = User::BOT_STATE_NORMAL;
                $user->save();

                $orders = Order::where('chat_id', $user->chat_id)
                    ->where('status', Order::STATUS_NEW)
                    ->get();

                $msg = __('bot.sell_settings.price_will_be_set', ['percent' => $price]);

                if ($orders->count()) {
                    $msg .= '. ' . __('bot.sell_settings.offers_will_be_canceled', ['count' => $orders->count()]);
                }

                $this->sendText($update, $msg);


                foreach ($orders as $order) {
                    $order->status = Order::STATUS_CANCELED_BY_SELLER;
                    $order->save();

                    $this->bot->sendMessage([
                        'chat_id' => $order->buyer_id,
                        'text' => __('bot.sell_settings.offers_will_be_canceled_buyer', [
                            'name' => $user->name,
                            'offer_id' => $order->id,
                        ])
                    ]);
                }

                return true;
        }

        return false;
    }

    public function processCallbackQuery($query, UpdateObject $update)
    {
        switch ($query) {
            case 'sell_disable':
                $this->toggleSell($update, false);
                $this->bot->triggerCommand('sell_settings', $update);
                return true;
            case 'sell_enable':
                $this->toggleSell($update, true);
                $this->bot->triggerCommand('sell_settings', $update);
                return true;
            case 'price_change':
                $this->startPriceChange($update);
                return true;
            case 'sell_change_cancel':
                $this->setState($update, User::BOT_STATE_NORMAL);
                $this->sendText($update, __('bot.sell_settings.save_canceled'));
                return true;
            default:
                foreach (PaymentMethod::all() as $pm) {
                    if ($update['callback_query']['data'] == ('sell_set_' . $pm->id)) {
                        $this->configureSellMethod($update, $pm);
                        return true;
                    }

                    if ($update['callback_query']['data'] == ('sell_disable_' . $pm->id)) {
                        $user = User::getUser($update->getChat()->id);
                        $user->paymentMethods()->detach($pm->id);
                        $this->bot->triggerCommand('sell_settings', $update);
                        sleep(.5);
                        $this->sendText($update, __('bot.sell_settings.method_disabled', [
                            'method' => $pm->name
                        ]));
                        return true;
                    }
                }
        }
    }

    public function configureSellMethod(UpdateObject $update, PaymentMethod $pm)
    {
        $user = User::getUser($update->getChat()->id);

        $current = '';

        $curDb = $user->paymentMethods()->where('id', $pm->id)->first();
        if ($curDb) {
            $disableText = [
                'reply_markup' => new Keyboard([
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false,
                    'inline_keyboard' => [
                        [
                            Keyboard::inlineButton([
                                'text' => __('bot.sell_settings.disable_method', [
                                    'method' => $pm->name
                                ]),
                                'callback_data' => 'sell_disable_' . $pm->id
                            ])
                        ],
                        [
                            Keyboard::inlineButton([
                                'text' => __('bot.sell_settings.save_current_data'),
                                'callback_data' => 'sell_change_cancel'
                            ])
                        ]
                    ]
                ])
            ];

            $current = "\n\n" . __('bot.sell_settings.current_data') . "\n{$curDb->pivot->info}";
        } else {
            $disableText = [
                'reply_markup' => new Keyboard([
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false,
                    'inline_keyboard' => [
                        [
                            Keyboard::inlineButton([
                                'text' => __('bot.sell_settings.cancel_new', ['method' => $pm->name]),
                                'callback_data' => 'sell_change_cancel'
                            ])
                        ]
                    ]
                ])
            ];
        }

        $user->bot_state = User::BOT_STATE_SELL_METHOD_SETTINGS;
        $user->save();

        $example = '';

        if ($pm->params) {
            $example = ".\n" . __('bot.sell_settings.as_example') . ": " . $pm->params;
        }

        $this->sendText($update,
            __('bot.sell_settings.type_payment_data', ['method' => $pm->name])
            . $example
            . $current,
            $disableText);

        $this->saveToState($user->chat_id . '_selected_method', $pm);
    }


    public function toggleSell($update, $active)
    {
        $user = User::getUser($update->getChat()->id);
        $user->offer_active = $active;
        $user->save();

        if ($active) {
            $this->sendText($update, __('bot.sell_settings.add_enabled'));
        } else {
            $this->sendText($update, __('bot.sell_settings.add_disabled'));
        }
    }


    public function startPriceChange($update)
    {
        $user = User::getUserByUpdate($update);
        $user->bot_state = User::BOT_STATE_CHANGE_PRICE;
        $user->save();

        $this->sendText($update, __('bot.sell_settings.input_price', [
            'from' => config('artr.min_price'),
            'to' => config('artr.max_price')
        ]));
    }
}
