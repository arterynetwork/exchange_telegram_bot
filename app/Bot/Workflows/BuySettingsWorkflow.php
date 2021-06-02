<?php


namespace App\Bot\Workflows;


use App\Models\PaymentMethod;
use App\Models\User;
use App\Classes\ArtrNode;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update as UpdateObject;

class BuySettingsWorkflow extends BaseWorkflow
{

    function processState(User $user, UpdateObject $update)
    {
        switch ($user->bot_state) {
            case User::BOT_STATE_BUY_SETTINGS_MIN:
                $amount = $this->inputAmount($update);
                if ($amount !== false) {
                    $this->sendText($update, __('bot.buy_settings.saved'));
                    $user->buy_min = round($amount * 1000000);
                    $user->save();
                    $this->showBuySettings($update);
                }
                return true;
            case User::BOT_STATE_BUY_SETTINGS_TOTAL:
                $amount = $this->inputAmount($update, $user->buy_min / 1000000);
                if ($amount !== false) {
                    $user->buy_total = round($amount * 1000000);
                    $user->save();
                    $this->sendText($update, __('bot.buy_settings.saved'));
                    $this->showBuySettings($update);
                }
                return true;
            case User::BOT_STATE_BUY_SETTINGS_PRICE:
                $course = ArtrNode::getStaticCourse();
                $amount = $this->inputAmount($update, 0, config('artr.max_percent'), 2);
                if ($amount !== false) {
                    $user->buy_p = $amount;
                    $user->buy_price = round(config('artr.price') * (1 + $amount / 100), 2);
                    $user->save();
                    $this->sendText($update, __('bot.buy_settings.saved'));
                    $this->showBuySettings($update);
                }
                return true;
            case User::BOT_STATE_BUY_SETTINGS_ADDRESS:
                $addr = ArtrNode::resolveCardNumber($update->message->text);

                \Log::debug(print_r($addr, 1));
                if (!$addr->address) {
                    $this->sendText($update, __('bot.common.settings.no_address'));
                } else {
                    $user->buy_address = $addr->address;
                    $user->buy_address_card = $update->message->text;
                    $user->bot_state = User::BOT_STATE_NORMAL;
                    $user->save();
                    $this->sendText($update, __('bot.buy_settings.saved'));
                    $this->showBuySettings($update);

                    return true;
                }

                return false;
        }
    }

    public function processCallbackQuery($query, UpdateObject $update)
    {
        switch ($query) {
            case 'buy_settings':
                $this->showBuySettings($update);
                return true;
            case 'buy_min_change':
                $this->sendText($update, __('bot.buy_settings.min_amount'));
                $this->setState($update, User::BOT_STATE_BUY_SETTINGS_MIN);
                return true;
            case 'buy_total_change':
                $this->sendText($update, __('bot.buy_settings.total_amount'));
                $this->setState($update, User::BOT_STATE_BUY_SETTINGS_TOTAL);
                return true;
            case 'buy_address_change':
                $this->sendText($update, __('bot.buy_settings.address'));
                $this->setState($update, User::BOT_STATE_BUY_SETTINGS_ADDRESS);
                return true;
            case 'buy_price_change':
                $course = ArtrNode::getStaticCourse();
                $this->sendText($update, __('bot.buy_settings.min_price', [
                    'from' => round(1000000 / $course['rub'], 2),
                    'to' => config('artr.max_price')
                ]));
                $this->setState($update, User::BOT_STATE_BUY_SETTINGS_PRICE);
                return true;
            case 'buy_offer_off':
                $user = User::getUserByUpdate($update);
                $user->buy_offer = false;
                $user->buy_expire = null;
                $user->save();
                $this->bot->editMessageText([
                    'chat_id' => $user->chat_id,
                    'message_id' => $update->getMessage()->messageId,
                    'text' => __('bot.buy_settings.offer_disabled'),
                    'reply_markup' => $this->getInlineKeyboardReply([[Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.enable'),
                        'callback_data' => 'buy_offer_on'
                    ])]])
                ]);

                try {
                    $msg = Cache::get('last_renew_msg_' . $user->chat_id);
                    $this->bot->deleteMessage([
                        'chat_id' => $user->chat_id,
                        'message_id' => $msg->messageId
                    ]);
                } catch (\Throwable $ex) {
                    \Log::error($ex);
                }

                return true;
            case 'buy_offer_on':
                $user = User::getUserByUpdate($update);

                if (!$user->buy_min) {
                    $this->sendText($update, __('bot.buy_settings.need_min_amount'));
                    return true;
                }
//
//                if (!$user->buy_total) {
//                    $this->sendText($update, __('bot.buy_settings.need_total_amount'));
//                    return true;
//                }

                if (!$user->buyMethods()->count()) {
                    $this->sendText($update, __('bot.buy_settings.need_payment_method'));
                    return true;
                }

                if (!$user->buy_address_card) {
                    $this->sendText($update, __('bot.buy_settings.need_address'));
                    return true;
                }

                $user->buy_offer = true;
                $user->buy_expire = now()->addDay();
                $user->save();
                $this->bot->editMessageText([
                    'chat_id' => $user->chat_id,
                    'message_id' => $update->getMessage()->messageId,
                    'text' => __('bot.buy_settings.offer_enabled') . ' ☑️',
                    'reply_markup' => $this->getInlineKeyboardReply([[Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.disable'),
                        'callback_data' => 'buy_offer_off'
                    ])]])
                ]);
                return true;
            case 'renew_buy_ad':
                $user = User::getUserByUpdate($update);
                if (!$user->buy_expire || $user->buy_expire < now()) {
                    $user->buy_expire = now()->addDay();
                } else {
                    $user->buy_expire = $user->buy_expire->addDay();
                }
                $user->save();
                $this->bot->sendMessage([
                    'chat_id' => $user->chat_id,
                    'text' => __('bot.buy_ads.renewed'),
                ]);

                try {
                    $this->bot->deleteMessage([
                        'chat_id' => $user->chat_id,
                        'message_id' => $update->getMessage()->messageId
                    ]);
                } catch (\Throwable $ex) {
                    \Log::error($ex);
                }

                return true;
            default:
                foreach (PaymentMethod::all() as $pm) {
                    if ($update['callback_query']['data'] == ('buy_mt_' . $pm->id)) {
                        $this->toggleBuyMethod($update, $pm);
                        return true;
                    }
                }
        }
    }

    public function toggleBuyMethod(UpdateObject $update, $pm)
    {
        $user = User::getUserByUpdate($update);

        if ($user->buyMethods()->where('id', $pm->id)->exists()) {
            $user->buyMethods()->detach([$pm->id]);
        } else {
            $user->buyMethods()->attach([$pm->id]);
        }

        $this->bot->editMessageReplyMarkup([
            'chat_id' => $user->chat_id,
            'message_id' => $update->callbackQuery->message->messageId,
            'reply_markup' => $this->getMethodsReplyMarkup($update, $user)
        ]);
    }

    public function getMethodsReplyMarkup(UpdateObject $update, User $user)
    {
        $keyboard = [];
        $activeMethods = $user->buyMethods()->pluck('name_ru', 'id');

        foreach (PaymentMethod::all() as $pm) {
            $active = '';
            if (isset($activeMethods[$pm->id])) {
                $active = ' ☑️';
            }

            $keyboard[] = [Keyboard::inlineButton([
                'text' => $pm->name . ' ' . $active,
                'callback_data' => 'buy_mt_' . $pm->id
            ])];
        }

        return new Keyboard([
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'inline_keyboard' => $keyboard
        ]);
    }

    public function showBuySettings(UpdateObject $update)
    {
        $user = User::getUserByUpdate($update);


        $this->bot->sendMessage(
            [
                'chat_id' => $user->chat_id,
                'text' => "<strong>" . __('bot.buy_settings.title') . "</strong>\n\n"
                    . "<strong>" . __('bot.buy_settings.select_methods') . "</strong>",
                'parse_mode' => 'HTML',
                'reply_markup' => $this->getMethodsReplyMarkup($update, $user)
            ]);

        $this->sendText($update,
            __('bot.buy_settings.current_min_amount', ['amount' => ArtrNode::formatAmount($user->buy_min)]),
            [
                'reply_markup' => $this->getInlineKeyboardReply([[Keyboard::inlineButton([
                    'text' => __('bot.common.buttons.edit'),
                    'callback_data' => 'buy_min_change'
                ])]])
            ]);
//        $this->sendText($update,
//            __('bot.buy_settings.current_total_amount', [
//                'amount' => ArtrNode::formatAmount($user->buy_total)
//            ]), [
//                'reply_markup' => $this->getInlineKeyboardReply([[Keyboard::inlineButton([
//                    'text' => 'Изменить',
//                    'callback_data' => 'buy_total_change'
//                ])]])
//            ]);

        $price = round(config('artr.price') * (1 + $user->buy_p / 100), 2);
        $user->buy_price = $price;
        $user->save();

        $this->sendText($update,
            __('bot.buy_settings.current_min_price', [
                'percent' => $user->buy_p,
                'price' => ArtrNode::getMultiCourseStringByRub($price, true)
            ]),
            [
                'reply_markup' => $this->getInlineKeyboardReply([[Keyboard::inlineButton([
                    'text' => __('bot.common.buttons.edit'),
                    'callback_data' => 'buy_price_change'
                ])]])
            ]);

        $this->sendText($update,
            __('bot.buy_settings.current_address', [
                'address' => ($user->buy_address_card ?? __('bot.buy_settings.no_current_address'))
            ]),
            [
                'reply_markup' => $this->getInlineKeyboardReply([[Keyboard::inlineButton([
                    'text' => __('bot.common.buttons.edit'),
                    'callback_data' => 'buy_address_change'
                ])]])
            ]);

        $this->sendText($update,
            $user->buy_offer
                ? __('bot.buy_settings.offer_enabled') . ' ☑'
                : __('bot.buy_settings.offer_disabled'),
            [
                'reply_markup' => $this->getInlineKeyboardReply([[Keyboard::inlineButton([
                    'text' => $user->buy_offer ? __('bot.common.buttons.disable') : __('bot.common.buttons.enable'),
                    'callback_data' => $user->buy_offer ? 'buy_offer_off' : 'buy_offer_on'
                ])]])
            ]);

        if ($user->buy_offer && $user->buy_expire < now()->addHour()) {
            $msg = Telegram::bot()->sendMessage([
                'chat_id' => $user->chat_id,
                'text' => __('bot.buy_ads.expiring'),
                'reply_markup' => new Keyboard([
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false,
                    'inline_keyboard' => [
                        [
                            Keyboard::inlineButton([
                                'text' => __('bot.buy_ads.renew'),
                                'callback_data' => 'renew_buy_ad'
                            ])
                        ],
                    ]
                ])
            ]);

            Cache::set('last_renew_msg_' . $user->chat_id, $msg);
        }
    }
}
