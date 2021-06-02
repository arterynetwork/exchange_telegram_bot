<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Send;
use App\Models\User;
use App\Classes\ArtrNode;
use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class ConfirmOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:confirm {orderId} {--yes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Confirm selected order';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $order = Order::find($this->argument('orderId'));

        if (!$order) {
            $this->error("Заказ не найден");
            return 0;
        }

        if ($order->status != Order::STATUS_PAYMENT_SEND) {
            $this->error("Статус заказа не верен: " . __(Order::STATUS_NAMES[$order->status]));
            return 0;
        }

        $order->status = Order::STATUS_COMPLETE_BY_BOT;
        $order->save();

        if (!$this->option('yes') && !$this->confirm("Подтвердить сделку № {$order->id} продавец {$order->chat_id} покупатель {$order->buyer_id} на "
            . ArtrNode::formatAmount($order->amount) . ' ARTR')) {
            return 0;
        }


        User::whereChatId($order->chat_id)->decrement('balance', $order->amount);
        User::whereChatId($order->chat_id)->decrement('locked', $order->amount);

        $fee = ArtrNode::getInComission($order->amount);
        $amount = $order->amount - $fee;

        $send = new Send();
        $send->order_id = $order->id;
        $send->address = $order->address;
        $send->amount = $amount;
        $send->save();

        Telegram::bot()->sendMessage([
            'chat_id' => $order->chat_id,
            'text' => __('bot.console.confirmed_seller', [
                'offer_id' => $order->id,
                'amount' => ArtrNode::formatAmount($order->amount)
            ])
        ]);

        Telegram::bot()->sendMessage([
            'chat_id' => $order->buyer_id,
            'text' => __('bot.console.confirmed_buyer', [
                'offer_id' => $order->id,
                'amount' => ArtrNode::formatAmount($order->amount),
                'card_number' => $order->card_number
            ])
        ]);

        return 0;
    }
}
