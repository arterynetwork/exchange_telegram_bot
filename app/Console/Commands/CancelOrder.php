<?php

namespace App\Console\Commands;

use App\Classes\ArtrNode;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;

class CancelOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:cancel {orderId} {--yes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel order with orderId';

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
            $this->error('Сделка не найдена');
            return 0;
        }

        if (!$this->option('yes') && !$this->confirm('Сделка № ' . $order->id . ' отменить и разблокировать средства продавцу?')) {
            $this->error('Операция отменена');
            return 0;
        }

        if ($order->status != Order::STATUS_IN_PROCESS && $order->status != Order::STATUS_PAYMENT_SEND) {
            $this->info('Сделка не в статусе процесс или оплата принята. Отмена не возможна');
            return 0;
        }

        $order->status = Order::STATUS_CANCELED_BY_BOT;
        User::where('chat_id', $order->chat_id)->decrement('locked', $order->amount);
        $order->save();

        \Telegram::bot()->sendMessage(['chat_id' => $order->chat_id,
            'text' => __('bot.console.canceled', [
                'offer_id' => $order->id,
                'amount' => ArtrNode::formatAmount($order->amount)
            ])
        ]);

        $this->info('Сделка отменена');

        return 0;
    }
}
