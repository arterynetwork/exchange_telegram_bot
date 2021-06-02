<?php


namespace App\Bot;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Classes\ArtrNode;
use Illuminate\Support\Facades\App;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;


class FullBuyHistoryCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "full_buy_history";

    /**
     * @var string Command Description
     */
    protected $description = "Завершенные покупки";

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $user = User::getUserByUpdate($this->update);

        $orders = Order::whereBuyerId($user->chat_id)
            ->whereNotIn('status', [
                Order::STATUS_NEW,
                Order::STATUS_IN_PROCESS,
                Order::STATUS_PAYMENT_SEND,
                Order::STATUS_BUYER_WAIT
            ])
            ->get();

        $pms = PaymentMethod::pluck("name_" . App::getLocale(), 'id');

        $this->replyWithMessage(['text' => __('bot.buy_history.full_list_title', ['count' => $orders->count()])]);

        foreach ($orders as $order) {
            $nick = User::getUser($order->chat_id)->name;

            $keyboard = [];

            if ($order->status == Order::STATUS_NEW) {
                $keyboard[] = [Keyboard::inlineButton([
                    'text' => __('bot.common.buttons.cancel2'),
                    'callback_data' => 'buy_cnclyn_' . $order->id,
                ])];
            }

            if ($order->status == Order::STATUS_IN_PROCESS) {
                $keyboard[] = [
                    Keyboard::inlineButton([
                        'text' => __('bot.buy_history.payment_sent'),
                        'callback_data' => 'buy_pay_' . $order->id,
                    ]),
                    Keyboard::inlineButton([
                        'text' => __('bot.common.buttons.cancel2'),
                        'callback_data' => 'buy_cnclyn_' . $order->id,
                    ])];
            }

            $this->replyWithMessage([
                'text' =>
                    "<strong>" . __('bot.buy_history.num') . "</strong>:{$order->id}"
                    . "\n<strong>" . __('bot.buy_history.seller') . "</strong>: " . $nick . "\n"
                    . "\n<strong>" . __('bot.buy_history.amount') . "</strong>: " . ArtrNode::formatAmount($order->amount) . ' ARTR'
                    . "\n<strong>" . __('bot.buy_history.method') . "</strong>: " . $pms[$order->payment_method_id]
                    . "\n<strong>" . __('bot.buy_history.currency_amount') . "</strong>: " . ArtrNode::getMultiCourseString(
                        $order->amount_currency,
                        $order->amount_usd,
                        $order->amount_byr,
                        $order->amount_uah,
                        $order->amount_kzt
                    )
                    . "\n<strong>" . __('bot.buy_history.wallet') . "</strong>: " . $order->card_number
                    . "\n<strong>" . __('bot.buy_history.status') . "</strong>: " . __(Order::STATUS_NAMES[$order->status]),
                'parse_mode' => 'HTML',
                'reply_markup' => new Keyboard([
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false,
                    'inline_keyboard' => $keyboard])
            ]);
        }
    }
}
