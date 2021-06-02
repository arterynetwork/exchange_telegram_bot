<?php


namespace App\Classes;


use App\Classes\ChainTransactions\Common;
use App\Classes\ChainTransactions\MsgSend;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

trait ArtrBlockTrait
{
    public static function queryMaxBlock()
    {
        return self::queryREST('blocks/latest');
    }

    public static function queryTransactions($height)
    {
        return self::queryREST('txs?tx.height=' . $height . '&limit=100000');
    }

    public static function getDate($date)
    {
        if (strlen($date) > 26) {
            $date = substr($date, 0, -3) . 'Z';
        }
        return new Carbon($date);
    }

    public static function findEventsByType($tx, $eventType)
    {
        $result = [];

        if (isset($tx['logs'][0]['events'])) {
            foreach ($tx['logs'][0]['events'] as $event) {
                if ($event['type'] == $eventType) {
                    $result[] = $event;
                }
            }
        }

        return $result;
    }

    public static function parseTransferEvent($tx)
    {
        $events = self::findEventsByType($tx, 'transfer');

        $result = [];
        $recipient = '';

        foreach ($events as $event) {
            foreach ($event['attributes'] as $attr) {
                if ($attr['key'] == 'recipient') {
                    $recipient = $attr['value'];
                }

                if ($attr['key'] == 'amount') {
                    if (isset($result[$recipient])) {
                        $result[$recipient] += (int)$attr['value'];
                    } else {
                        $result[$recipient] = (int)$attr['value'];
                    }
                }
            }
        }

        return $result;
    }

    public static function hasEvents($tx, $eventType)
    {
        return isset($tx['logs']['0']['events']) &&
            (count(self::findEventsByType($tx, $eventType)) > 0);
    }

    public static function loadBlock($curBlock)
    {
        $processors = [
            'cosmos-sdk/MsgSend' => MsgSend::class,
        ];


        $bTxs = self::queryTransactions($curBlock);

        foreach ($bTxs['txs'] as $tx) {
            try {
                $type = $tx['tx']['value']['msg'][0]['type'];

                /** @var Common $t */
                $t = null;

                if (isset($processors[$type])) {
                    $t = new $processors[$type]($tx);
                } else {
                    continue;
                }


                $newTx = $t->getTransaction();
                $newTx->block_id = $curBlock;

                if ($newTx->recipient != config('artr.bot_address')
                    && $newTx->sender != config('artr.bot_address')) {
                    continue;
                }

                if (Transaction::whereHash($newTx->hash)->exists()) {
                    continue;
                }

                $memo = trim($newTx->data['tx']['value']['memo']);
                $newTx->memo = $memo;
                $user = null;


                if ($newTx->recipient == config('artr.bot_address') && $newTx->status == 0) {
                    if (is_numeric($memo)) {
                        $user = User::whereChatId(intval($memo) ^ 23545364534)->first();
                    }

                    if (!$user) {
                        $newTx->returnable = true;
                    }
                }

                $newTx->save();

                if ($user) {
                    $user->increment('balance', $newTx->amount);

                    try {
                        \Telegram::bot()->sendMessage([
                            'chat_id' => $user->chat_id,
                            'text' => __('bot.console.balance_credited', [
                                'amount' => ArtrNode::formatAmount($newTx->amount)
                            ]),
                        ]);
                    } catch (\Throwable $ex) {
                        Log::error($ex);
                    }
                }

                $w = Withdraw::whereTxhash($newTx->hash)->first();

                if ($newTx->sender == config('artr.bot_address') && $w) {
                    if ($newTx->status == 0) {
                        $w->status = 1;
                        $w->save();

                        $profile = ArtrNode::getProfile($newTx->recipient);

                        \Telegram::bot()->sendMessage([
                            'chat_id' => $w->chat_id,
                            'text' => __('bot.console.withdrawed', [
                                'amount' => ArtrNode::formatAmount($w->amount),
                                'fee' => ArtrNode::formatAmount($newTx->fee),
                                'address' => ArtrNode::formatArteryAddress($profile->profile->card_number)
                            ]),
                        ]);
                    } else {
                        $w->status = 2;
                        $w->save();
                    }
                }

                $order = Order::where('txhash', mb_strtoupper($newTx->hash))
                    ->where('tx_status', Order::TX_STATUS_SEND)
                    ->first();

                if ($order) {
                    if ($newTx->status == 0) {
                        $order->tx_status = Order::TX_STATUS_CONFIRMED;
                        $order->save();
                        \Telegram::bot()->sendMessage([
                            'chat_id' => $order->buyer_id,
                            'text' => __('bot.console.payed', [
                                'amount' => ArtrNode::formatAmount($order->amount),
                                'fee' => ArtrNode::formatAmount($newTx->fee),
                                'address' => $order->card_number
                            ]),
                        ]);
                    } else {
                        $order->status = Order::TX_STATUS_ERRORED;
                        $order->save();
                    }
                }

            } catch (\Throwable $ex) {
                \Log::error($ex);
            }
        }
    }
}
