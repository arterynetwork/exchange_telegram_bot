<?php


namespace App\Bot;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;


class StartCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "start";

    /**
     * @var string Command Description
     */
    protected $description = "Команда Start для начала работы с ботом";

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $keyboard = [
            [
                __('bot.start.wallet'),
                __('bot.start.sell'),
                __('bot.start.buy')
            ],
            [
                __('bot.start.history'),
                __('bot.start.rules'),
            ]
        ];

        $reply_markup = new Keyboard([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);

        $this->replyWithMessage([
            'text' => __('bot.start.welcome'),
            'reply_markup' => $reply_markup
        ]);
    }
}
