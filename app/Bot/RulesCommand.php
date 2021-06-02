<?php


namespace App\Bot;

use Telegram\Bot\Commands\Command;


class RulesCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "rules";

    /**
     * @var string Command Description
     */
    protected $description = "Правила сервиса";

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $this->replyWithMessage([
            'text' => __('bot.rules.link'),
            'parse_mode' => 'html'
        ]);
    }
}
