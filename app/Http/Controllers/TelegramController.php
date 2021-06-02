<?php

namespace App\Http\Controllers;

use App\Bot\Workflows\BaseWorkflow;
use App\Bot\Workflows\HistoryWorkflow;
use App\Bot\Workflows\OrderWorkflow;
use App\Bot\Workflows\BuySettingsWorkflow;
use App\Bot\Workflows\BuyAdsWorkflow;
use App\Bot\Workflows\SellSettingsWorkflow;
use App\Bot\Workflows\WithdrawWorkflow;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Classes\ArtrNode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update as UpdateObject;

class TelegramController extends Controller
{
    protected $workflows = [];

    public function __construct()
    {
        // Modules
        $this->workflows['sell_settings'] = new SellSettingsWorkflow();
        $this->workflows['order'] = new OrderWorkflow();
        $this->workflows['withdraw'] = new WithdrawWorkflow();
        $this->workflows['history'] = new HistoryWorkflow();
        $this->workflows['buy_settings'] = new BuySettingsWorkflow();
        $this->workflows['buy_adds'] = new BuyAdsWorkflow();
    }

    public function deleteMessage($update)
    {
        // Uncomment, if needed
//        Telegram::bot()->deleteMessage([
//            'chat_id' => $update['message']['chat']['id'],
//            'message_id' => $update['message']['message_id'],
//        ]);
    }

    public function sendText(UpdateObject $update, $text, $params = [])
    {
        Telegram::bot()->sendMessage(array_merge([
            'chat_id' => $update->getChat()->id,
            'text' => $text
        ], $params));
    }

    public function setState(UpdateObject $update, $state)
    {
        $user = User::getUser($update->getChat()->id);
        $user->bot_state = $state;
        $user->save();
    }

    public function processState(UpdateObject $update)
    {
        $user = User::getUser($update->getChat()->id);
        $text = $update->getMessage()->text;

        \Log::debug('Processing state ' . $user);

        /** @var BaseWorkflow $workflow */
        $result = false;
        foreach ($this->workflows as $workflow) {
            $result = $workflow->processState($user, $update);
            if ($result) {
                break;
            }
        }

        if ($result) {
            return true;
        }

        switch ($user->bot_state) {
            case User::BOT_STATE_BUY_AMOUNT:
                $text = str_replace(',', '.', trim($text));
                if (!is_numeric($text)) {
                    $this->sendText($update, __('bot.validation.is_nan'));
                } else {
                    $amount = round(floatval($text) * 1000000);

                    if ($amount <= 0) {
                        $this->sendText($update, __('bot.validation.need_positive'));
                        break;
                    }

                    Cache::set($user->chat_id . '_buy_amount', $amount);
                    $this->sendText($update, __('bot.buy_ads.wallet'));
                    $user->bot_state = User::BOT_STATE_BUY_WALLET;
                    $user->save();
                }

                break;
            case User::BOT_STATE_BUY_WALLET:
                $addr = ArtrNode::resolveCardNumber($text);

                if (mb_strtoupper(trim($text)) == 'ARTR-1122-3600-2050'
                    || mb_strtoupper(trim($text)) == 'ARTR-1122-3600-2004') {
                    $this->sendText($update, __('bot.common.settings.only_app'));
                    break;
                }

                \Log::debug(print_r($addr, 1));
                if (!$addr->address) {
                    $this->sendText($update, __('bot.buy_ads.no_address'));
                } else {
                    Cache::set($user->chat_id . '_buy_address', $addr->address);
                    Cache::set($user->chat_id . '_buy_card_number', $text);
                    $amount = Cache::get($user->chat_id . '_buy_amount');
                    $user->bot_state = User::BOT_STATE_BUY_METHOD;
                    $user->save();

                    $keyboard = [];

                    foreach (PaymentMethod::all() as $pm) {
                        $cnt = User::whereOfferActive(true)
                            ->where('banned', false)
                            ->whereHas('paymentMethods', function ($q) use ($pm) {
                                $q->whereId($pm->id);
                            })
                            ->where(DB::raw('balance - locked'), '>=', $amount)
                            ->where('balance', '>=', $amount)
                            ->count();

                        $keyboard[] = [Keyboard::inlineButton([
                            'text' => $pm->name . ' (' . $cnt . ')',
                            'callback_data' => 'buy_set_' . $pm->id
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
                break;
            case User::BOT_STATE_CONFIRMATION_IMAGE:
                if (!$update->getChat()->photo) {
                    $this->sendText($update, __('bot.buy_ads.need_image'));
                }
                break;
            default:

        }
    }

    public function configureBuyMethod(UpdateObject $update, PaymentMethod $pm)
    {
        $user = User::getUserByUpdate($update);
        $amount = Cache::get($user->chat_id . '_buy_amount');

        if (!$amount) {
            $this->sendText($update, 'Не указана сумма');
            return;
        }

        $offers = User::whereOfferActive(true)
            ->where('banned', false)
            ->whereHas('paymentMethods', function ($q) use ($pm) {
                $q->whereId($pm->id);
            })
            ->where(DB::raw('balance - locked'), '>=', $amount)
            ->where('balance', '>=', $amount)
            ->inRandomOrder()
            ->get();

        $this->sendText($update, __('bot.buy_ads.ads_by_method')
            . "\n*" . __('bot.common.order.amount') . "*: " . ArtrNode::formatAmount($amount) . ' '
            . "ARTR\n*" . __('bot.common.order.method') . "*: " . $pm->name, ['parse_mode' => 'Markdown']);

        $course = ArtrNode::getStaticCourse();

        foreach ($offers as $offer) {
            $amountPrice = round($amount / 1000000 * $offer->token_price, 2);

            $messageToSend = '';
            try {
                $messageToSend = '<b>' . __('bot.common.order.seller') . '</b>: ' . htmlentities($offer->name) . "\n"
                    . "<b>" . __('bot.common.order.reserve') . "</b>: " . ArtrNode::formatAmount($offer->balance - $offer->locked)
                    . "\n<b>" . __('bot.common.order.coin_price') . "</b>: " . ArtrNode::getMultiCourseStringByRub($offer->token_price)
                    . "\n<b>" . __('bot.common.order.amount_price',
                        ['amount' => ArtrNode::formatAmount($amount)]) . '</b>: ' . ArtrNode::getMultiCourseStringByRub($amountPrice);
                $this->sendText($update,
                    $messageToSend,
                    [
                        'parse_mode' => 'HTML',
                        'reply_markup' => new Keyboard([
                            'resize_keyboard' => true,
                            'one_time_keyboard' => false,
                            'inline_keyboard' => [
                                [Keyboard::inlineButton([
                                    'text' => __('bot.buy_ads.give_order'),
                                    'callback_data' => 'buy_r_' . $pm->id . '_' . $offer->chat_id . '_' . $offer->token_price,
                                ])]]
                        ])]
                );
            } catch (\Throwable $ex) {
                \Log::debug('Offer data: ' . print_r($offer->toArray(), 1));
                \Log::debug($messageToSend);
                \Log::error($ex);
            }
        }

        $this->sendText($update, __('bot.buy_ads.count', [
            'count' => $offers->count()
        ]));
    }

    public function hook()
    {
        \App::setLocale('ru');
        Config::set('artr.price', 10000);
        try {
            try {
                $course = ArtrNode::getStaticCourse();
                Config::set('artr.min_price', round(1000000 / $course['rub'] * 1.03, 2));
                Config::set('artr.price', round(1000000 / $course['rub'], 2));
                Config::set('artr.max_price', round(1000000 / $course['rub'] * 1.05, 2));
            } catch (\Throwable $er) {
                \Log::error($er);
            }

            /** @var UpdateObject $update */
            $update = Telegram::bot()->getWebhookUpdate();

            if ($update->getChat()->id) {
                $lang = 'ru';

                try {
                    $lang = $update->getMessage()->from->languageCode;
                } catch (\Throwable $er) {
                    \Log::error($er);
                }

                $u = User::getUser($update->getChat()->id, $lang);
                if ($u) {
                    if ($u->banned) {
                        if ($u->reason) {
                            $this->sendText($update, __('bot.hook.locked_reason')
                                . $u->reason . "\n" . __('bot.hook.locked_support'));
                        } else {
                            $this->sendText($update, __('bot.hook.locked_no_reason')
                                . "\n" . __('bot.hook.locked_support'));
                        }
                        return 'ok';
                    }

                    if ($u->name != $update->getChat()->firstName) {
                        $u->name = $update->getChat()->firstName;
                        $u->save();
                    }

                    \App::setLocale('ru');
                }
            }

            if (isset($update['message']['text'])) {
                switch (mb_strtolower($update['message']['text'])) {
                    case mb_strtolower(__('bot.start.wallet')):
                        $this->deleteMessage($update);
                        Telegram::bot()->triggerCommand('wallet', $update);
                        break;
                    case mb_strtolower(__('bot.start.sell')):
                        $this->deleteMessage($update);
                        Telegram::bot()->triggerCommand('sell_menu', $update);
                        break;
                    case mb_strtolower(__('bot.start.buy')):
                        $this->deleteMessage($update);
                        Telegram::bot()->triggerCommand('buy_menu', $update);
                        break;
                    case mb_strtolower(__('bot.start.history')):
                        $this->deleteMessage($update);
                        Telegram::bot()->triggerCommand('history', $update);
                        break;
                    case mb_strtolower(__('bot.start.rules')):
                        $this->deleteMessage($update);
                        Telegram::bot()->triggerCommand('rules', $update);
                        break;
                    default:
                        Telegram::bot()->processCommand($update);
                        $this->processState($update);
                }
            } else {
                if ($update->getMessage()->photo || $update->getMessage()->document) {
                    $this->processPhoto($update);
                    $r = false;
                    /** @var BaseWorkflow $workflow */
                    $u = User::getUserByUpdate($update);
                    foreach ($this->workflows as $workflow) {
                        $r = $workflow->processPhoto($u, $update);
                        if ($r) {
                            break;
                        }
                    }
                }

                if (isset($update['callback_query'])) {
                    $r = false;
                    /** @var BaseWorkflow $workflow */
                    foreach ($this->workflows as $workflow) {
                        $r = $workflow->processCallbackQuery($update['callback_query']['data'], $update);
                        if ($r) {
                            break;
                        }
                    }

                    if (!$r) {
                        switch ($update['callback_query']['data']) {
                            // Пополнение кошелька
                            case 'fill_up':
                                $this->setState($update, User::BOT_STATE_NORMAL);
                                $this->sendText($update, __('bot.hook.credit_wallet'));
                                $this->sendText($update, config('artr.bot_wallet'));
                                $this->sendText($update, __('bot.hook.credit_code'));
                                $this->sendText($update, $update->getChat()->id ^ 23545364534);
                                $this->sendText($update, __('bot.hook.credit_warn'));
                                break;
                            case 'buy_ads':
                                Telegram::bot()->triggerCommand('buy_ads', $update);
                                break;
                            case 'sell_settings':
                                Telegram::bot()->triggerCommand('sell_settings', $update);
                                break;
                            case 'cancel_changes':
                                $this->sendText($update, __('bot.common.cancel_changes'));
                                $this->setState($update, User::BOT_STATE_NORMAL);
                                break;
                            default:
                                foreach (PaymentMethod::all() as $pm) {
                                    if ($update['callback_query']['data'] == ('buy_set_' . $pm->id)) {
                                        $this->configureBuyMethod($update, $pm);
                                        break;
                                    }
                                }
                                Telegram::bot()->processCommand($update);
                        }
                    }

                    try {
                        Telegram::bot()->answerCallbackQuery(['callback_query_id' => $update['callback_query']['id']]);
                    } catch (TelegramResponseException $tex) {
                        try {
                            \Log::channel('telegram')->error($tex);
                        } catch (\Throwable $ex) {
                            \Log::error($ex);
                        }
                    } catch (\Throwable $er) {
                        \Log::error($er);
                    }
                } else {
                    Telegram::bot()->processCommand($update);
                }
            }
        } catch (TelegramResponseException $tex) {
            try {
                \Log::channel('telegram')->error($tex);
            } catch (\Throwable $ex) {
                \Log::error($ex);
            }
        } catch (\Throwable $ex) {
            \Log::error($ex);
        }
        return 'ok';
    }
}
