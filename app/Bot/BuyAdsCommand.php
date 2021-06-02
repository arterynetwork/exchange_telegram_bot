<?php


namespace App\Bot;

use App\Models\User;
use Telegram\Bot\Commands\Command;


class BuyAdsCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "buy_ads";

    /**
     * @var string Command Description
     */
    protected $description = "Купить ARTR";

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $user = User::getUserByUpdate($this->update);

        $user->bot_state = User::BOT_STATE_BUY_AMOUNT;
        $user->save();

        $this->replyWithMessage([
            'text' => __('bot.buy_ads.amount'),
        ]);
    }
}
