<?php


namespace App\Bot\Workflows;


use App\Models\Send;
use App\Models\User;
use App\Models\Withdraw;
use App\Classes\ArtrNode;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update as UpdateObject;

class WithdrawWorkflow extends BaseWorkflow
{
    function processState(User $user, UpdateObject $update)
    {
        $text = $update->getMessage()->text;

        switch ($user->bot_state) {
            case User::BOT_STATE_WITHDRAW_AMOUNT:
                $text = str_replace(',', '.', trim($text));
                if (!is_numeric($text)) {
                    $this->sendText($update, __('bot.validation.is_nan'));
                } else {
                    $amount = round(floatval($text) * 1000000);

                    if ($amount <= 0) {
                        $this->sendText($update, __('bot.validation.need_positive'));
                        break;
                    }

                    if ($amount > $user->balance
                        || $amount > ($user->balance - $user->locked)) {
                        $lockedBalance = $user->balance - $user->locked;
                        if ($lockedBalance > $user->balance) {
                            $lockedBalance = $user->balance;
                        }

                        if ($lockedBalance < 0) {
                            $lockedBalance = 0;
                        }

                        $this->sendText($update,
                            __('bot.withdraw.insufficient_fund', [
                                'balance' => ArtrNode::formatAmount($lockedBalance),
                                'amount' => ArtrNode::formatAmount($amount)
                            ]));
                    } else {
                        $user->bot_state = User::BOT_STATE_WITHDRAW_WALLET;
                        Cache::set($user->chat_id . '_withdraw_amount', $amount);
                        $user->save();
                        $this->sendText($update, __('bot.withdraw.input_address', ['amount' => ArtrNode::formatAmount($amount)]));
                    }
                }
                return true;
            case User::BOT_STATE_WITHDRAW_WALLET:
                $addr = ArtrNode::resolveCardNumber($text);

                if(mb_strtoupper(trim($text)) == 'ARTR-1122-3600-2050'
                || mb_strtoupper(trim($text)) == 'ARTR-1122-3600-2004'){
                    $this->sendText($update, __('bot.common.settings.only_app'));
                    return true;
                }

                \Log::debug(print_r($addr, 1));
                if (!$addr->address) {
                    $this->sendText($update, __('bot.common.settings.no_address'));
                } else {
                    $user->out_address = $addr->address;
                    $user->bot_state = User::BOT_STATE_WITHDRAW_CONFIRM;
                    $user->save();

                    $amount = Cache::get($user->chat_id . '_withdraw_amount');

                    $keyboard = [
                        [
                            Keyboard::inlineButton([
                                'text' => __('bot.common.buttons.confirm'),
                                'callback_data' => 'withdraw_confirm'
                            ])
                        ]
                    ];

                    $this->sendText($update, __('bot.withdraw.please_confirm')
                        . "\n" . __('bot.withdraw.wallet') . ": " . $text
                        . "\n" . __('bot.withdraw.amount') . ": " . ArtrNode::formatAmount($amount) . ' ARTR'
                        . "\n" . __('bot.withdraw.fee') . ": " . ArtrNode::formatAmount(ArtrNode::getInComission($amount)) . ' ARTR',
                        [
                            'reply_markup' => new Keyboard([
                                'resize_keyboard' => true,
                                'one_time_keyboard' => false,
                                'inline_keyboard' => $keyboard
                            ])
                        ]);
                }
                return true;
        }

        return false;
    }

    public function processCallbackQuery($query, UpdateObject $update)
    {
        switch ($query) {
            case 'withdraw':
                $this->setState($update, User::BOT_STATE_WITHDRAW_AMOUNT);
                $this->sendText($update, __('bot.withdraw.input_amount'));
                return true;
            case 'withdraw_confirm':
                $this->confirmWithdraw($update);
                return true;
        }

        return false;
    }

    public function confirmWithdraw($update)
    {
        $user = User::getUser($update->getChat()->id);

        if ($user->bot_state != User::BOT_STATE_WITHDRAW_CONFIRM) {
            $this->sendText($update, __('bot.withdraw.canceled'));
            return;
        }

        $amount = Cache::get($user->chat_id . '_withdraw_amount');

        if ($amount > $user->balance || $amount > ($user->balance - $user->locked)) {
            $this->sendText($update, __('bot.withdraw.insufficient_fund', [
                    'balance' => ArtrNode::formatAmount($user->balance),
                    'amount' => ArtrNode::formatAmount($amount)
                ])
            );
            return;
        }

        $this->sendWithdraw($user, $user->out_address, $amount, __('bot.withdraw.transaction_comment'));

        $user->bot_state = User::BOT_STATE_NORMAL;
        $user->save();

        $this->sendText($update, __('bot.withdraw.withdraw_sent'));
    }

    public function sendWithdraw(User $user, $address, $amount, $comment)
    {
        $fee = ArtrNode::getInComission($amount);
        $w = new Withdraw();
        $w->chat_id = $user->chat_id;
        $w->account_address = $address;
        $w->account_card = '';
        $w->amount = $amount;
        $w->fee = $fee;
        $w->save();

        $amount -= $fee;
        User::whereChatId($user->chat_id)->decrement('balance', $amount + $fee);

        try {
            $send = new Send();
            $send->withdraw_id = $w->id;
            $send->address = $address;
            $send->amount = $amount;
            $send->save();

        } catch (\Throwable $er) {
            \Log::error($er);
        }
    }
}
