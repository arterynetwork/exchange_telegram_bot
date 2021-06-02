<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Classes\ArtrNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class CloseOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'offers:close {--loop}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close offers active for to long';

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
        do {
            Order::where('created_at', '<=', now()->subHours(3))
                ->whereIn('status', [Order::STATUS_NEW, Order::STATUS_BUYER_WAIT])
                ->chunk(500, function ($orders) {
                    foreach ($orders as $order) {
                        try {
                            $this->info('Offer #' . $order->id . ' closed');
                            $order->status = Order::STATUS_EXPIRE;
                            $order->save();
//                        User::whereChatId($order->chat_id)->decrement('locked', $order->amount);

                            $user = User::getUser($order->chat_id);
                            Telegram::bot()->sendMessage([
                                'chat_id' => $order->buyer_id,
                                'text' => __('bot.console.close_buyer', [
                                    'name' => $user->name,
                                    'amount' => ArtrNode::formatAmount($order->amount)
                                ])
                            ]);

                            $user = User::getUser($order->buyer_id);
                            Telegram::bot()->sendMessage([
                                'chat_id' => $order->chat_id,
                                'text' => __('bot.console.close_seller', [
                                    'name' => $user->name,
                                    'amount' => ArtrNode::formatAmount($order->amount)
                                ])
                            ]);
                        } catch (\Throwable $ex) {
                            Log::error($ex);
                        }
                    }
                });

            Order::whereStatus(Order::STATUS_PAYMENT_SEND)
                ->whereNotNull('remind_at')
                ->where('remind_at', '<=', now())
                ->chunk(500, function ($orders) {
                    foreach ($orders as $order) {
                        try {
                            $buyer = User::getUser($order->buyer_id);
                            Telegram::bot()->sendMessage([
                                'chat_id' => $order->chat_id,
                                'text' => __('bot.console.remind_seller', [
                                    'offer_id' => $order->id,
                                    'name' => $buyer->name,
                                ]),
                                'reply_markup' => new Keyboard([
                                    'resize_keyboard' => true,
                                    'one_time_keyboard' => true,
                                    'inline_keyboard' => [
                                        [
                                            Keyboard::inlineButton([
                                                'text' => __('bot.common.buttons.confirm'),
                                                'callback_data' => 'buy_comp_' . $order->id,
                                            ]),
                                            Keyboard::inlineButton([
                                                'text' => __('bot.common.buttons.support'),
                                                'url' => 'tg://resolve?domain=artrsupport',
                                            ]),
                                        ]
                                    ]
                                ]),
                            ]);

                            $order->remind_at = now()->addMinutes(15);
                            $order->save();
                        } catch (\Throwable $ex) {
                            Log::error($ex);
                            $order->remind_at = null;
                            $order->save();
                        }
                    }
                });

            User::whereNotNull('buy_expire')
                ->where('buy_expire', '<', now())->where('buy_offer', 1)
                ->chunk(1000, function ($users) {
                    foreach ($users as $user) {
                        try {
                            $user->buy_offer = false;
                            $user->buy_expire = null;
                            $user->save();

                            Telegram::bot()->sendMessage([
                                'chat_id' => $user->chat_id,
                                'text' => __('bot.buy_ads.expired')
                            ]);
                        } catch (\Throwable $ex) {
                            Log::error($ex);
                        }
                    }
                });

            User::whereNotNull('buy_expire')
                ->where('buy_expire', '>', now()->addHour())
                ->where('buy_expire', '<=', now()->addHour()->addMinute())
                ->where('buy_offer', 1)
                ->chunk(1000, function ($users) {
                    foreach ($users as $user) {
                        try {
                            Telegram::bot()->sendMessage([
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
                        } catch (\Throwable $ex) {
                            Log::error($ex);
                        }
                    }
                });

            if ($this->option('loop')) {
                sleep(60);
            }
        } while ($this->option('loop'));
    }
}
