<?php


namespace App\Bot;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Classes\ArtrNode;
use Illuminate\Support\Facades\App;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;


class FullSellHistoryCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "full_sell_history";

    /**
     * @var string Command Description
     */
    protected $description = "Завершенные продажи";

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $user = User::getUserByUpdate($this->update);

        $orders = Order::whereChatId($user->chat_id)
            ->whereNotIn('status', [
                Order::STATUS_NEW,
                Order::STATUS_IN_PROCESS,
                Order::STATUS_PAYMENT_SEND,
                Order::STATUS_BUYER_WAIT,
            ])
            ->get();

        $pms = PaymentMethod::pluck("name_" . App::getLocale(), 'id');

        $this->replyWithMessage(['text' => __('bot.sell_history.full_list_title', ['count' => $orders->count()])]);

        foreach ($orders as $order) {
            $nick = User::getUser($order->buyer_id)->name;

            $keyboard = [];

            if ($order->status == Order::STATUS_NEW) {
                $keyboard[] = [Keyboard::inlineButton([
                    'text' => __('bot.common.buttons.apply'),
                    'callback_data' => 'buy_conf_' . $order->id,
                ])];
            }

            if ($order->status == Order::STATUS_IN_PROCESS) {
                if ($order->created_at->diffInMinutes(now()) >= Order::MINUTES_TO_PAY) {
                    $keyboard[] = [Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.cancel2'),
                        'callback_data' => 'buy_cncl_' . $order->id,
                    ])];
                }
            }

            if ($order->status == Order::STATUS_PAYMENT_SEND) {
                $keyboard[] = [
                    Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.confirm'),
                        'callback_data' => 'buy_comp_' . $order->id,
                    ]),
                    Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.support'),
                        'url' => 'tg://resolve?domain=artrsupport',
                    ])];
            }

            if ($order->status != Order::STATUS_PAYMENT_SEND || !$order->screenshot) {
                $this->replyWithMessage([
                    'text' =>
                        "<strong>" . __('bot.buy_history.num') . "</strong>: {$order->id}"
                        . "\n<strong>" . __('bot.buy_history.buyer') . "</strong>: " . $nick . "\n"
                        . "\n<strong>" . __('bot.buy_history.amount') . "</strong>: " . ArtrNode::formatAmount($order->amount) . ' ARTR'
                        . "\n<strong>" . __('bot.buy_history.method') . "</strong>: " . $pms[$order->payment_method_id]
                        . "\n<strong>" . __('bot.buy_history.currency_amount') . "</strong>: " . ArtrNode::getMultiCourseString(
                            $order->amount_currency,
                            $order->amount_usd,
                            $order->amount_byr,
                            $order->amount_uah,
                            $order->amount_kzt
                        )
                        . "\n<strong>" . __('bot.buy_history.status') . "</strong>: " . __(Order::STATUS_NAMES[$order->status]),
                    'parse_mode' => 'HTML',
                    'reply_markup' => new Keyboard([
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false,
                        'inline_keyboard' => $keyboard])
                ]);
            } else {
                $params = [
                    'caption' =>
                        "<strong>" . __('bot.buy_history.num') . "</strong>: {$order->id}"
                        . "\n<strong>" . __('bot.buy_history.buyer') . "</strong>: " . $nick . "\n"
                        . "\n<strong>" . __('bot.buy_history.amount') . "</strong>: " . ArtrNode::formatAmount($order->amount) . ' ARTR'
                        . "\n<strong>" . __('bot.buy_history.method') . "</strong>: " . $pms[$order->payment_method_id]
                        . "\n<strong>" . __('bot.buy_history.currency_amount') . "</strong>: " . ArtrNode::getMultiCourseString(
                            $order->amount_currency,
                            $order->amount_usd,
                            $order->amount_byr,
                            $order->amount_uah,
                            $order->amount_kzt
                        )
                        . "\n<strong>" . __('bot.buy_history.status') . "</strong>: " . __(Order::STATUS_NAMES[$order->status])
                        . "\n"
                        . "\n" . __('bot.order.payment_image')
                        . "\n"
                        . "\n" . __('bot.order.payment_warning'),
                    'parse_mode' => 'HTML',
                    'reply_markup' => new Keyboard([
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false,
                        'inline_keyboard' => $keyboard])
                ];

                if ($order->doctype == 'photo') {
                    $params['photo'] = $order->screenshot;
                    $this->replyWithPhoto($params);
                } else {
                    $params['document'] = $order->screenshot;
                    $params['chat_id'] = $this->update->getChat()->id;
                    Telegram::bot()->sendDocument($params);
                }
            }
        }
    }
}
