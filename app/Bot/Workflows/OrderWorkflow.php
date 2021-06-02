<?php


namespace App\Bot\Workflows;


use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Send;
use App\Models\User;
use App\Classes\ArtrNode;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update as UpdateObject;

class OrderWorkflow extends BaseWorkflow
{
    function processState(User $user, UpdateObject $update)
    {
    }

    public function processCallbackQuery($query, UpdateObject $update)
    {
        switch ($query) {
            default:
                if (strpos($update['callback_query']['data'], 'buy_r_') === 0) {
                    $this->startOffer($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'buy_conf_') === 0) {
                    $this->confirmOffer($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'buy_cnclyn_') === 0) {
                    $this->cancelOfferYesNo($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'buy_cncl_') === 0) {
                    $this->cancelOffer($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'buy_no_cncl_') === 0) {
                    $this->offerNotCanceled($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'buy_attrs_') === 0) {
                    $this->attrOffer($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'buy_pay_') === 0) {
                    $this->payOffer($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'buy_comp_') === 0) {
                    $this->completeOffer($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'buy_close_') === 0) {
                    $this->closeOffer($update);
                    return true;
                }

                if (strpos($update['callback_query']['data'], 'buy_not_') === 0) {
                    $this->notCloseOffer($update);
                    return true;
                }
        }

        return false;
    }

    public function processPhoto(User $user, UpdateObject $update)
    {
        switch ($user->bot_state) {
            case User::BOT_STATE_CONFIRMATION_IMAGE:
                $maxSize = 0;
                $bigPhoto = null;

                \Log::debug(Cache::get($user->chat_id . '_current_offer'));

                $photos = $update->getMessage()->photo;
                $type = 'photo';

                if ($photos) {
                    foreach ($photos as $photo) {
                        if ($photo['file_size'] > $maxSize) {
                            $maxSize = $photo['file_size'];
                            $bigPhoto = $photo;
                        }
                    }
                } else {
                    $bigPhoto = $update->getMessage()->document;
                    $type = 'document';
                }


                $offerId = Cache::get($user->chat_id . '_current_offer');
                $offer = Order::find($offerId);

                if (!$offer) {
                    $this->sendText($update, __('bot.order.no_order_selected'));
                    break;
                }

                if ($offer->status != Order::STATUS_IN_PROCESS) {
                    $this->sendText($update, __('bot.order.cant_confirm_by_status', [
                        'status' => __(Order::STATUS_NAMES[$offer->status])
                    ]));
                    break;
                }

                $offer->screenshot = $bigPhoto['file_id'];
                $offer->status = Order::STATUS_PAYMENT_SEND;
                $offer->remind_at = now()->addMinutes(15);
                $offer->doctype = $type;
                $offer->save();

                $user->bot_state = User::BOT_STATE_NORMAL;
                $user->save();

                $this->sendText($update, __('bot.order.payment_confirmed_message', ['offer_id' => $offer->id]));

                $params = [
                    'chat_id' => $offer->chat_id,
                    'caption' =>
                        __('bot.order.payment_message', [
                            'name' => $user->name,
                            'offer_id' => $offer->id,
                            'amount' => ArtrNode::formatAmount($offer->amount)
                        ])
                        . "\n" . __('bot.order.payment_image')
                        . "\n"
                        . "\n" . __('bot.order.payment_warning'),
                    'reply_markup' => new Keyboard([
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false,
                        'inline_keyboard' => [
                            [
                                Keyboard::inlineButton([
                                    'text' => __('bot.common.buttons.confirm'),
                                    'callback_data' => 'buy_comp_' . $offer->id,
                                ]),
                                Keyboard::inlineButton([
                                    'text' => __('bot.common.buttons.support'),
                                    'url' => 'tg://resolve?domain=artrsupport',
                                ])]]
                    ])
                ];

                if ($type == 'photo') {
                    $params['photo'] = $offer->screenshot;
                    Telegram::bot()->sendPhoto($params);
                } else {
                    $params['document'] = $offer->screenshot;
                    Telegram::bot()->sendDocument($params);
                }

                return true;
            default:
        }

        return false;
    }

    public function startOffer($update)
    {
        $data = $update['callback_query']['data'];

        $data = explode('_', $data);
        $pm = intval($data[2]);
        $chatId = intval($data[3]);
        $price = floatval($data[4]);

        $user = User::getUserByUpdate($update);
        $amount = Cache::get($user->chat_id . '_buy_amount');
        $address = Cache::get($user->chat_id . '_buy_address');
        $cardNumber = Cache::get($user->chat_id . '_buy_card_number');


        // проверить баланс и включен ли способ оплаты
        $seller = User::getUser($chatId);
        if ((($seller->balance - $seller->locked) < $amount) || ($seller->balance < $amount)) {
            $this->sendText($update, __('bot.order.insufficient_seller_funds'));
            return;
        }

        $pm = PaymentMethod::find($pm);

        if (!$seller->paymentMethods()->where('id', $pm->id)->exists()) {
            $this->sendText($update, __('bot.order.seller_close_method', ['method' => $pm->name]));
            return;
        }

        \Log::debug('Price ' . $price . ' course ' . $seller->token_price . ' ' . $seller->name);

        if ($seller->token_price != $price) {
            $this->sendText($update, __('bot.order.seller_changed_price'));
            return;
        }

        $curAmount = round($amount / 1000000 * $seller->token_price, 2);

        if (Order::where('buyer_id', $user->chat_id)
            ->where('chat_id', $seller->chat_id)
            ->where('amount', $amount)
            ->where('payment_method_id', $pm->id)
            ->where('status', Order::STATUS_NEW)
            ->count()) {
            $this->sendText($update, __('bot.order.already_offered'));
            return;
        }

        if (Order::where('buyer_id', $user->chat_id)
                ->whereIn('status', [
                    Order::STATUS_NEW,
                    Order::STATUS_IN_PROCESS,
                    Order::STATUS_PAYMENT_SEND,
                    Order::STATUS_BUYER_WAIT
                ])
                ->count() >= 3) {
            $this->sendText($update, __('bot.order.no_more'));
            return;
        }


        $o = new Order();
        $o->buyer_id = $user->chat_id;
        $o->chat_id = $seller->chat_id;
        $o->amount = $amount;
        $o->amount_currency = $curAmount;
        try {
            $courseAll = ArtrNode::getStaticCourse();

            \Log::debug('New order: ' . print_r([
                    'course' => $courseAll,
                    'curAmount' => $curAmount,
                ], true));

            $o->amount_byr = round($curAmount / $courseAll['byr-rub'], 6);
            $o->amount_uah = round($curAmount / $courseAll['uah-rub'], 6);
            $o->amount_kzt = round($curAmount / $courseAll['kzt-rub'], 6);
            $o->amount_usd = round($amount / $courseAll['usd'], 6);
        } catch (\Throwable $ex) {
            \Log::error($ex);
        }

        $o->payment_method_id = $pm->id;
        $o->address = $address;
        $o->card_number = $cardNumber;
        $o->status = 0;
        $o->save();

        $this->sendText($update, __('bot.order.offer_sent'));
        $this->bot->sendMessage([
            'chat_id' => $seller->chat_id,
            'text' =>
                "✅ " . __('bot.order.new_offer') . "\n"
                . "\n"
                . "\n" . __('bot.common.order.amount') . ": " . ArtrNode::formatAmount($amount)
                . "\n" . __('bot.common.order.price') . ": " . ArtrNode::getMultiCourseStringByRub($o->amount_currency * 1000000 / $o->amount)
                . "\n" . __('bot.common.order.method') . ": {$pm->name}"
                . "\n" . __('bot.common.order.user') . ": {$user->name}"
                . "\n"
                . "\n" . __('bot.common.order.total') . ": " . ArtrNode::getMultiCourseString(
                    $o->amount_currency,
                    $o->amount_usd,
                    $o->amount_byr,
                    $o->amount_uah,
                    $o->amount_kzt
                ),
            'reply_markup' => $this->getInlineKeyboardReply([
                [
                    Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.apply'),
                        'callback_data' => 'buy_conf_' . $o->id,
                    ]),
                    Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.cancel'),
                        'callback_data' => 'buy_cncl_' . $o->id,
                    ])]])
        ]);
    }

    public function confirmOfferBuyer(UpdateObject $update, Order $offer)
    {
        if ($offer->buyer_id != $update->getChat()->id) {
            $this->sendText($update, __('bot.order.status_edit_not_available'));
            return;
        }

        $seller = User::getUser($offer->chat_id);
        $user = User::getUserByUpdate($update);

        if (($seller->balance - $seller->locked) < $offer->amount
            || $seller->balance < $offer->amount) {
            $this->sendText($update, __('bot.order.insufficient_seller_funds'));
            return;
        }

        $offer->status = Order::STATUS_IN_PROCESS;
        try {
            $offer->created_at = now();
        } catch (\Throwable $ex) {
            \Log::error($ex);
        }
        $pmInfo = $seller->paymentMethods()->where('id', $offer->payment_method_id)->first()->pivot->info;
        $offer->info = $pmInfo;
        $offer->save();

        User::whereChatId($offer->chat_id)->increment('locked', $offer->amount);


        $this->bot->sendMessage([
            'chat_id' => $offer->buyer_id,
            'text' => __('bot.order.order_confirmed', [
                    'offer_id' => $offer->id, 'amount' => ArtrNode::formatAmount($offer->amount)])
                . "\n\n" . __('bot.order.payment_data', ['offer_id' => $offer->id])
                . "\n"
                . $pmInfo
                . "\n\n"
                . __('bot.order.order_confirmed_history')
        ]);

        $this->bot->sendMessage([
            'chat_id' => $offer->chat_id,
            'text' => __('bot.order.order_confirmed_seller', [
                'name' => $user->name,
                'offer_id' => $offer->id,
                'amount' => ArtrNode::formatAmount($offer->amount)
            ])
        ]);

    }

    public function confirmOffer(UpdateObject $update)
    {
        $offerId = intval(str_replace('buy_conf_', '', $update['callback_query']['data']));

        $offer = Order::whereId($offerId)->first();

        if ($offer->status == Order::STATUS_BUYER_WAIT) {
            return $this->confirmOfferBuyer($update, $offer);
        }

        if ($offer->chat_id != $update->getChat()->id) {
            $this->sendText($update, __('bot.order.status_edit_not_available'));
            return;
        }

        if ($offer->status != Order::STATUS_NEW) {
            $this->sendText($update, __('bot.order.cant_apply_by_status', [
                'status' => __(Order::STATUS_NAMES[$offer->status])
            ]));
            return;
        }

        $user = User::getUserByUpdate($update);

        if (($user->balance - $user->locked) < $offer->amount) {
            $this->sendText($update, __('bot.order.insufficient_funds'));
            return;
        }

        $offer->status = Order::STATUS_IN_PROCESS;

        try {
            $offer->created_at = now();
            $pmInfo = $user->paymentMethods()->where('id', $offer->payment_method_id)->first()->pivot->info;
            $offer->info = $pmInfo;
        } catch (\Throwable $ex) {
        }
        $offer->save();

        User::whereChatId($offer->chat_id)->increment('locked', $offer->amount);

        $this->sendText($update, __('bot.order.order_confirmed_message', ['offer_id' => $offer->id]));

        $this->bot->sendMessage([
            'chat_id' => $offer->buyer_id,
            'text' =>
                __('bot.order.order_confirmed_buyer', [
                    'name' => $user->name,
                    'offer_id' => $offer->id,
                    'amount' => ArtrNode::formatAmount($offer->amount)
                ])
                . "\n\n" . __('bot.order.payment_data', ['offer_id' => $offer->id])
                . "\n"
                . $user->paymentMethods()->where('id', $offer->payment_method_id)->first()->pivot->info
                . "\n\n"
                . __('bot.order.order_confirmed_history')
        ]);
    }

    public function cancelOfferYesNo(UpdateObject $update)
    {
        $offerId = intval(str_replace('buy_cnclyn_', '', $update['callback_query']['data']));

        $offer = Order::whereId($offerId)->first();

        try {
            $this->bot->sendMessage([
                'chat_id' => $offer->buyer_id,
                'text' => __('bot.order.confirm_cancel', [
                    'offer_id' => $offer->id,
                ]),
                'reply_markup' => new Keyboard([
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                    'inline_keyboard' => [[
                        Keyboard::inlineButton([
                            'text' => __('bot.common.buttons.yes'),
                            'callback_data' => 'buy_cncl_' . $offer->id,
                        ]),
                        Keyboard::inlineButton([
                            'text' => __('bot.common.buttons.no'),
                            'callback_data' => 'buy_no_cncl_' . $offer->id,
                        ])
                    ]],
                ])
            ]);
        } catch (\Throwable $ex) {
            \Log::error($ex);
        }
    }

    public function offerNotCanceled(UpdateObject $update)
    {
        $offerId = intval(str_replace('buy_no_cncl_', '', $update['callback_query']['data']));
        $offer = Order::whereId($offerId)->first();
        try {
            $this->sendText(
                $update,
                __('bot.order.order_not_canceled', [
                    'offer_id' => $offer->id
                ])
            );
        } catch (\Throwable $ex) {
            \Log::error($ex);
        }
    }

    public function cancelOffer(UpdateObject $update)
    {
        $offerId = intval(str_replace('buy_cncl_', '', $update['callback_query']['data']));

        $offer = Order::whereId($offerId)->first();

        try {
            $chatId = $update->getChat()->id;
            if ($offer->chat_id != $chatId && $offer->buyer_id != $chatId) {
                $this->sendText($update, __('bot.order.status_edit_not_available'));
                return;
            }

            $user = User::getUser($chatId);

            if ($offer->status != Order::STATUS_NEW
                && $offer->status != Order::STATUS_IN_PROCESS
                && $offer->status != Order::STATUS_BUYER_WAIT) {
                $this->sendText($update, __('bot.order.cant_cancel_by_status', [
                    'status' => __(Order::STATUS_NAMES[$offer->status])
                ]));
                return;
            }

            if ($offer->chat_id == $chatId) {
                if ($offer->status == Order::STATUS_IN_PROCESS && $offer->created_at->diffInMinutes(now()) < Order::MINUTES_TO_PAY) {
                    $this->sendText($update, __('bot.order.wait_an_hour'));
                    return;
                }

                if ($offer->status == Order::STATUS_IN_PROCESS) {
                    User::whereChatId($offer->chat_id)->decrement('locked', $offer->amount);
                }

                $offer->status = Order::STATUS_CANCELED_BY_SELLER;
                $this->sendText($update, __('bot.order.order_canceled', [
                    'offer_id' => $offer->id
                ]));


                $this->bot->sendMessage([
                    'chat_id' => $offer->buyer_id,
                    'text' => __('bot.order.order_canceled_seller', [
                        'name' => $user->name,
                        'offer_id' => $offer->id,
                        'amount' => ArtrNode::formatAmount($offer->amount)
                    ])
                ]);
            }

            if ($offer->buyer_id == $chatId) {
                if ($offer->status == Order::STATUS_IN_PROCESS) {
                    User::whereChatId($offer->chat_id)->decrement('locked', $offer->amount);
                }

                $offer->status = Order::STATUS_CANCELED_BY_BUYER;
                $this->sendText($update, __('bot.order.order_canceled', [
                    'offer_id' => $offer->id
                ]));

                try {
                    $this->bot->sendMessage([
                        'chat_id' => $offer->chat_id,
                        'text' => __('bot.order.order_canceled_buyer', [
                            'name' => $user->name,
                            'offer_id' => $offer->id,
                            'amount' => ArtrNode::formatAmount($offer->amount)
                        ])
                    ]);
                } catch (\Throwable $er) {

                }
            }
        } catch (\Throwable $ex) {
            \Log::error($ex);
        } finally {
            $offer->save();
        }
    }


    public function attrOffer(UpdateObject $update)
    {
        $offerId = intval(str_replace('buy_attrs_', '', $update['callback_query']['data']));

        $offer = Order::whereId($offerId)->first();

        $seller = User::getUser($offer->chat_id);

        $this->bot->sendMessage([
            'chat_id' => $offer->buyer_id,
            'text' => __('bot.order.payment_data', ['offer_id' => $offer->id]) . "\n"
                . $seller->paymentMethods()->where('id', $offer->payment_method_id)->first()->pivot->info
                . "\n\n"
                . __('bot.order.order_confirmed_history')
        ]);
    }

    public function payOffer(UpdateObject $update)
    {
        $offerId = intval(str_replace('buy_pay_', '', $update['callback_query']['data']));

        $offer = Order::whereId($offerId)->first();

        $chatId = $update->getChat()->id;
        if ($offer->buyer_id != $chatId) {
            $this->sendText($update, __('bot.order.status_edit_not_available'));
            return;
        }

        if ($offer->status != Order::STATUS_IN_PROCESS) {
            $this->sendText($update, __('bot.order.cant_confirm_order_by_status', [
                'status' => __(Order::STATUS_NAMES[$offer->status])
            ]));
            return;
        }

        $user = User::getUser($chatId);
        $user->bot_state = User::BOT_STATE_CONFIRMATION_IMAGE;
        $user->save();

        $this->sendText($update, __('bot.order.send_confirmation'));

        Cache::set($user->chat_id . '_current_offer', $offer->id);

        //
        return;
    }

    public function completeOffer(UpdateObject $update)
    {
        $offerId = intval(str_replace('buy_comp_', '', $update['callback_query']['data']));

        $offer = Order::whereId($offerId)->first();

        $chatId = $update->getChat()->id;
        if ($offer->chat_id != $chatId) {
            $this->sendText($update, __('bot.order.status_edit_not_available'));
            return;
        }

        if ($offer->status != Order::STATUS_PAYMENT_SEND) {
            $this->sendText($update, __('bot.order.cant_apply_by_status', [
                'status' => __(Order::STATUS_NAMES[$offer->status])
            ]));
            return;
        }

        $this->sendText($update, __('bot.order.confirm_warning'), [
            'reply_markup' => $this->getInlineKeyboardReply([
                [
                    Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.confirm'),
                        'callback_data' => 'buy_close_' . $offer->id,
                    ]),
                    Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.cancel'),
                        'callback_data' => 'buy_not_' . $offer->id,
                    ])]])
        ]);
    }

    public function closeOffer($update)
    {
        $offerId = intval(str_replace('buy_close_', '', $update['callback_query']['data']));

        $offer = Order::whereId($offerId)->first();

        $chatId = $update->getChat()->id;
        if ($offer->chat_id != $chatId) {
            $this->sendText($update, __('bot.order.status_edit_not_available'));
            return;
        }

        if ($offer->status == Order::STATUS_COMPLETE) {
            $this->sendText($update, __('bot.order.already_closed'));
            return;
        }

        if ($offer->status != Order::STATUS_PAYMENT_SEND) {
            $this->sendText($update, __('bot.order.invalid_status'));
            return;
        }

        $user = User::getUser($chatId);
        if ($offer->amount > $user->balance) {
            $this->sendText($update, __('bot.order.insufficient_funds_for_close'));
            return;
        }

        $offer->status = Order::STATUS_COMPLETE;
        $offer->save();

        $this->sendText($update, "☑️ " . __('bot.order.coins_sent', ['offer_id' => $offer->id]));


        //TODO: списание средств через блокчейн
        User::whereChatId($user->chat_id)->decrement('balance', $offer->amount);
        //TODO: проверка, заблокированы средства по сделке или еще нет
        User::whereChatId($user->chat_id)->decrement('locked', $offer->amount);

        try {
            $fee = ArtrNode::getInComission($offer->amount);
            $amount = $offer->amount - $fee;

            $send = new Send();
            $send->order_id = $offer->id;
            $send->address = $offer->address;
            $send->amount = $amount;
            $send->save();

//            $result = ArtrNode::sendMoney(config('artr.bot_address'), $offer->address, $amount, '', '');
//            \Log::debug('Result: ' . print_r($result, 1));
//            if (isset($result->txhash)) {
//                $offer->txhash = $result->txhash;
//            $offer->tx_status = 1;
//            $offer->save();
//            }
        } catch (\Throwable $er) {
            \Log::error($er);
        }

        $this->bot->sendMessage([
            'chat_id' => $offer->buyer_id,
            'text' => __('bot.order.coins_sent_buyer', [
                'name' => $user->name,
                'offer_id' => $offer->id,
                'card_number' => $offer->card_number
            ])
        ]);
    }

    public function notCloseOffer($update)
    {
        $offerId = intval(str_replace('buy_not_', '', $update['callback_query']['data']));

        $offer = Order::whereId($offerId)->first();

        $chatId = $update->getChat()->id;
        if ($offer->chat_id != $chatId) {
            $this->sendText($update, __('bot.order.status_edit_not_available'));
            return;
        }

        if ($offer->status == Order::STATUS_COMPLETE) {
            $this->sendText($update, __('bot.order.already_closed'));
            return;
        }

        if ($offer->status != Order::STATUS_PAYMENT_SEND) {
            $this->sendText($update, __('bot.order.invalid_status'));
            return;
        }

        $this->sendText($update, __('bot.order.order_not_closed'));
    }
}
