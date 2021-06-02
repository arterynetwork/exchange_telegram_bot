<?php


namespace App\Bot;

use App\Models\User;
use App\Classes\ArtrNode;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;


class WalletCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "wallet";

    /**
     * @var string Command Description
     */
    protected $description = "Открыть настройки кошелька";

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $keyboard = [
            [
                Keyboard::inlineButton([
                    'text' => __('bot.wallet.fill_up'),
                    'callback_data' => 'fill_up'
                ]),
                Keyboard::inlineButton([
                    'text' => __('bot.wallet.withdraw'),
                    'callback_data' => 'withdraw'
                ])],
        ];

        $reply_markup = new Keyboard([
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'inline_keyboard' => $keyboard
        ]);

        $user = User::getUser($this->getUpdate()->getChat()->id);

        $this->replyWithMessage([
            'text' => __('bot.wallet.title') . "\n\n"
                . __('bot.wallet.balance') . ": "
                . ArtrNode::formatAmount($user->balance)
                . " ARTR (" . ArtrNode::getMulticourseStringByArtr($user->balance, true) . ")"
                . "\n" . __('bot.wallet.locked') . ' '
                . ArtrNode::formatAmount($user->locked)
                . " ARTR (" . ArtrNode::getMulticourseStringByArtr($user->locked, true) . ")"
                . "\n" . __('bot.wallet.sell_ad') . " " . ($user->offer_active ? __('bot.wallet.enabled') . ' ☑' : __('bot.wallet.disabled'))
                . "\n" . __('bot.wallet.buy_ad') . " " . ($user->buy_offer ? __('bot.wallet.enabled') . ' ☑' : __('bot.wallet.disabled')),
            'reply_markup' => $reply_markup
        ]);
    }
}
