<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Send;
use App\Models\Withdraw;
use App\Classes\ArtrNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendTransactions extends Command
{
    const MAX_TXS_PER_BLOCK = 30;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:send {--loop}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send transactions to users (withdraws and order payments)';

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
            $queueEmpty = true;

            $wallet = ArtrNode::getWallet(config('artr.bot_address'));

            Send::where('status', 0)
                ->chunk(static::MAX_TXS_PER_BLOCK, function ($sends) use (&$queueEmpty, &$wallet) {
                    foreach ($sends as $send) {
                        $this->info('processing send #' . $send->id);
                        $seqNo = Cache::rememberForever('seqNo', function () {
                            $acc = ArtrNode::queryAccount(config('artr.bot_address'));
                            $ps = $acc['result']['value']['sequence'];
                            return $ps;
                        });

                        $result = ArtrNode::sendMoney(config('artr.bot_address'), $send->address, $send->amount, $wallet['privateKey'], '', $seqNo);
                        $this->info('Result: ' . print_r($result, 1));
                        if (isset($result->txhash)) {
                            if (isset($result->code) && $result->code == 4) {
                                $this->info('error! retry in the next iteration');
                                continue;
                            }

                            Cache::increment('seqNo');
                            $send->status = 1;
                            $send->save();

                            if ($send->withdraw_id) {
                                $w = Withdraw::find($send->withdraw_id);
                                $w->txhash = $result->txhash;
                                $w->save();
                            } elseif ($send->order_id) {
                                $o = Order::find($send->order_id);
                                $o->txhash = $result->txhash;
                                $o->tx_status = Order::TX_STATUS_SEND;
                                $o->save();
                            }
                        }
                    }
                    $queueEmpty = false;
                    sleep(40);
                });
            if ($queueEmpty)
                sleep(10);
        } while ($this->option('loop'));
    }
}
