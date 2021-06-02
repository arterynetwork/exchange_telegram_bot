<?php


namespace App\Bot\Workflows;


use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Classes\ArtrNode;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update as UpdateObject;

class BuyAdsWorkflow extends BaseWorkflow
{

    function processState(User $user, UpdateObject $update)
    {
        switch ($user->bot_state) {
            case User::BOT_STATE_SELL_ADS_AMOUNT:
                $user = User::getUserByUpdate($update);
                $pm = PaymentMethod::find($this->getFromState('sell_add_pm_' . $user->chat_id));
                $offer = User::getUser($this->getFromState('sell_add_chat_id_' . $user->chat_id));

                $balance = $user->balance - $user->locked;
                if ($balance > $user->balance) {
                    $balance = $user->balance;
                }

                $amount = $this->inputAmount($update, $offer->buy_min / 1000000, $balance / 1000000, 2);

                if ($amount === false) {
                    return true;
                }

                $this->saveToState('sell_add_amount_' . $user->chat_id, $amount * 1000000);
                $user->bot_state = User::BOT_STATE_SELL_ADS_PRICE;
                $user->save();

                $this->sendText($update, __('bot.sell_ads.write_price', [
                    'from' => $offer->buy_price,
                    'to' => config('artr.max_price')
                ]));

                return true;
            case User::BOT_STATE_SELL_ADS_PRICE:
                $user = User::getUserByUpdate($update);
                $pm = PaymentMethod::find($this->getFromState('sell_add_pm_' . $user->chat_id));
                $offer = User::getUser($this->getFromState('sell_add_chat_id_' . $user->chat_id));

                $price = $this->inputAmount($update, $offer->buy_price, config('artr.max_price') * 1.005, 2);

                if ($price === false) {
                    return true;
                }

                $this->saveToState('sell_add_price_' . $user->chat_id, $price);

                $user->bot_state = User::BOT_STATE_SELL_ADS_CONFIRM;
                $user->save();

                $amount = $this->getFromState('sell_add_amount_' . $user->chat_id);

                $this->sendText($update,
                    __('bot.sell_ads.send_question',
                        [
                            'buyer_name' => $offer->name,
                            'amount' => ArtrNode::formatAmount($amount),
                            'price' => $price,
                            'currency_amount' => ArtrNode::getMultiCourseStringByRub(round($price * $amount / 1000000, 2), true)
                        ]
                    ),
                    [
                        'reply_markup' => $this->getInlineKeyboardReply([
                            [
                                Keyboard::inlineButton([
                                    'text' => __('bot.common.buttons.confirm'),
                                    'callback_data' => 'sell_ads_confirm'
                                ]),
                                Keyboard::inlineButton([
                                    'text' => __('bot.common.buttons.delete'),
                                    'callback_data' => 'sell_ads_cancel'
                                ])]
                        ])
                    ]);

                return true;
        }

        return false;
    }

    public function processCallbackQuery($query, UpdateObject $update)
    {
        switch ($query) {
            case 'sell_ads':
                $this->showSellAdsPM($update);
                return true;
            case 'sell_ads_cancel':
                $this->bot->deleteMessage(['chat_id' => $update->getChat()->id, 'message_id' => $update->callbackQuery->message->messageId]);
                $this->setState($update, User::BOT_STATE_NORMAL);
                return true;
            case 'sell_ads_confirm':
                $this->confirmSellOrder($update);
                return true;
            default:
                if (strpos($update['callback_query']['data'], 'buy_ad_') === 0) {
                    $this->giveOffer($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'sell_ad_l_') === 0) {
                    $this->showSellAds($update);
                    return true;
                }
        }
    }

    public function confirmSellOrder(UpdateObject $update)
    {
        $user = User::getUserByUpdate($update);
        $pm = PaymentMethod::find($this->getFromState('sell_add_pm_' . $user->chat_id));
        $offer = User::getUser($this->getFromState('sell_add_chat_id_' . $user->chat_id));
        $amount = $this->getFromState('sell_add_amount_' . $user->chat_id);
        $price = $this->getFromState('sell_add_price_' . $user->chat_id);

        if ($amount > $user->balance
            || $amount > ($user->balance - $user->locked)) {
            $this->sendText($update, __('bot.sell_ads.insufficient_funds'));
            return;
        }

        if (!$offer->buy_address) {
            $this->sendText($update, __('bot.sell_ads.missing_buyer_address'));
            return;
        }


        $order = new Order();
        $order->chat_id = $user->chat_id;
        $order->buyer_id = $offer->chat_id;
        $order->is_sell = true;
        $order->status = Order::STATUS_BUYER_WAIT;
        $order->amount = $amount;
        $order->payment_method_id = $pm->id;
        $order->amount_currency = round($amount * $price / 1000000, 2);

        try {
            $courseAll = ArtrNode::getStaticCourse();

            \Log::debug('New order: ' . print_r([
                    'course' => $courseAll,
                    'curAmount' => $order->amount_currency,
                ], true));

            $order->amount_byr = round($order->amount_currency / $courseAll['byr-rub'], 6);
            $order->amount_uah = round($order->amount_currency / $courseAll['uah-rub'], 6);
            $order->amount_kzt = round($order->amount_currency / $courseAll['kzt-rub'], 6);
            $order->amount_usd = round($amount / $courseAll['usd'], 6);
        } catch (\Throwable $ex) {
            \Log::error($ex);
        }
        $order->address = $offer->buy_address;
        $order->card_number = $offer->buy_address_card;
        $order->save();


        $this->sendText($update, __('bot.sell_ads.order_send'));
        $this->bot->sendMessage([
            'chat_id' => $offer->chat_id,
            'text' => "✅ " .
                __('bot.sell_ads.order_info.title') .
                "\n" .
                "\n" . __('bot.common.order.amount') . ": " . ArtrNode::formatAmount($amount) .
                "\n" . __('bot.common.order.price') . ": " . ArtrNode::getMultiCourseStringByRub($order->amount_currency * 1000000 / $order->amount) .
                "\n" . __('bot.common.order.method') . ": {$pm->name}" .
                "\n" . __('bot.common.order.user') . ": {$user->name}" .
                "\n" .
                "\n" . __('bot.common.order.total') . ": " . ArtrNode::getMultiCourseString(
                    $order->amount_currency,
                    $order->amount_usd,
                    $order->amount_byr,
                    $order->amount_uah,
                    $order->amount_kzt
                ),
            'reply_markup' => $this->getInlineKeyboardReply([
                [
                    Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.apply'),
                        'callback_data' => 'buy_conf_' . $order->id,
                    ]),
                    Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.cancel'),
                        'callback_data' => 'buy_cncl_' . $order->id,
                    ])]])
        ]);

    }

    public function giveOffer(UpdateObject $update)
    {
        $user = User::getUserByUpdate($update);
        $data = explode('_', $update->callbackQuery->data);
        $pm = PaymentMethod::find($data[2]);
        $offer = User::getUser($data[3]);

        if (($user->balance - $user->locked) < $offer->buy_min
            || $user->balance < $offer->buy_min
            || $user->locked < 0) {
            $this->sendText($update, __('bot.sell_ads.insufficient_funds_for_sell'));
            return;
        }

        $user->bot_state = User::BOT_STATE_SELL_ADS_AMOUNT;
        $user->save();
        $balance = $user->balance - $user->locked;
        if ($balance > $user->balance) {
            $balance = $user->balance;
        }

        $balance = ArtrNode::formatAmount($balance);
        $buyMin = ArtrNode::formatAmount($offer->buy_min);

        $this->saveToState('sell_add_pm_' . $user->chat_id, $pm->id);
        $this->saveToState('sell_add_chat_id_' . $user->chat_id, $offer->chat_id);

        $this->sendText($update, __('bot.sell_ads.amount_to_sell', [
            'min' => $buyMin,
            'max' => $balance
        ]));
    }

    public function showSellAdsPM(UpdateObject $update)
    {
        $keyboard = [];

        foreach (PaymentMethod::all() as $pm) {
            $cnt = User::whereBuyOffer(true)
                ->where('banned', false)
                ->whereHas('buyMethods', function ($q) use ($pm) {
                    $q->whereId($pm->id);
                })
                ->count();

            $keyboard[] = [Keyboard::inlineButton([
                'text' => $pm->name . ' (' . $cnt . ')',
                'callback_data' => 'sell_ad_l_' . $pm->id
            ])];
        }

        $reply_markup = new Keyboard([
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'inline_keyboard' => $keyboard
        ]);

        $this->sendText(
            $update,
            __('bot.buy_ads.select_payment_method'),
            [
                'reply_markup' => $reply_markup
            ]);
    }

    public function showSellAds(UpdateObject $update)
    {
        $user = user::getUserByUpdate($update);

        $data = explode('_', $update->callbackQuery->data);
        $pm = PaymentMethod::find(array_pop($data));

        $offers = User::whereBuyOffer(true)
            ->where('banned', false)
            ->whereHas('buyMethods', function ($q) use ($pm) {
                $q->where('id', $pm->id);
            })
            ->with(['buyMethods'])
            ->inRandomOrder()
            ->get();

        if (!$offers->count()) {
            $this->sendText($update, __('bot.sell_ads.no_offers'));
        } else {
            $this->sendText($update, __('bot.sell_ads.found_offers', [
                'count' => $offers->count()
            ]));
        }

        $course = ArtrNode::getStaticCourse();

        $paymentMethods = $user->paymentMethods()->pluck('id');

        foreach ($offers as $offer) {
            $pms = $offer->buyMethods()->get();

            $amountPrice = round($offer->buy_min * $offer->buy_price / 1000000, 2);

            $amountPriceUSD = round($offer->buy_min * $offer->buy_price / 1000000 / $course['usd-rub'], 2);

            $messageToSend = '';

            $keyboard = [];
            foreach ($pms->whereIn('id', $paymentMethods) as $pm) {
                $keyboard[] = [Keyboard::inlineButton([
                    'text' => __('bot.sell_ads.send_offer_thru', ['method' => $pm->name]),
                    'callback_data' => 'buy_ad_' . $pm->id . '_' . $offer->chat_id,
                ])];
            }

            try {
                $messageToSend = '<b>' . __('bot.common.order.buyer') . '</b>: ' . htmlentities($offer->name) . "\n"
                    . "<b>" . __('bot.common.order.min_amount') . "</b>: " . ArtrNode::formatAmount($offer->buy_min)
                    . "\n<b>" . __('bot.common.order.min_price') . "</b>: " . ARtrNode::getMultiCourseStringByRub($offer->buy_price)
//                    . "\n<b>Стоимость от</b>: " . $amountPrice . '₽, ('
//                    . $amountPriceUSD . '$)'
                    . "\n<b>" . __('bot.common.order.methods') . "</b>: " . $pms->pluck('name')->join(', ');
                $this->sendText($update,
                    $messageToSend,
                    [
                        'parse_mode' => 'HTML',
                        'reply_markup' => new Keyboard([
                            'resize_keyboard' => true,
                            'one_time_keyboard' => false,
                            'inline_keyboard' => $keyboard
                        ])]
                );
            } catch (\Throwable $ex) {
                \Log::debug('Offer data: ' . print_r($offer->toArray(), 1));
                \Log::debug($messageToSend);
                \Log::error($ex);
            }
        }
    }
}
