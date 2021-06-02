<?php


namespace App\Bot;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;


class HistoryCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "history";

    /**
     * @var string Command Description
     */
    protected $description = "История операций";

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $keyboard = [
            [
                Keyboard::inlineButton([
                    'text' => __('bot.history.buts'),
                    'callback_data' => 'buy_history'
                ])], [
                Keyboard::inlineButton([
                    'text' => __('bot.history.sells'),
                    'callback_data' => 'sell_history'
                ])], [
                Keyboard::inlineButton([
                    'text' => __('bot.history.all_buys'),
                    'callback_data' => 'full_buy_history'
                ])], [
                Keyboard::inlineButton([
                    'text' => __('bot.history.all_sells'),
                    'callback_data' => 'full_sell_history'
                ])],
        ];

        $reply_markup = new Keyboard([
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'inline_keyboard' => $keyboard
        ]);

        $this->replyWithMessage([
            'text' => __('bot.history.title'),
            'reply_markup' => $reply_markup
        ]);
    }
}
