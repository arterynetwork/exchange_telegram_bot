<?php


namespace App\Bot;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;


class BuyMenuCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "buy_menu";

    /**
     * @var string Command Description
     */
    protected $description = "Меню покупки монет";

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $keyboard = [
            [
                Keyboard::inlineButton([
                    'text' => __('bot.buy_menu.my_ad'),
                    'callback_data' => 'buy_settings'
                ])], [
                Keyboard::inlineButton([
                    'text' => __('bot.buy_menu.other_ads'),
                    'callback_data' => 'buy_ads'
                ])]
        ];

        $reply_markup = new Keyboard([
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'inline_keyboard' => $keyboard
        ]);

        $this->replyWithMessage([
            'text' => __('bot.buy_menu.select_method'),
            'reply_markup' => $reply_markup
        ]);
    }
}
