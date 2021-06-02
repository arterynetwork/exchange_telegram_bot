<?php


namespace App\Bot;

use App\Models\PaymentMethod;
use App\Models\User;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;


class BuySettingsCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "sell_settings";

    /**
     * @var string Command Description
     */
    protected $description = "Продать ARTR";

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $user = User::getUser($this->update->getChat()->id);


        // Способы оплаты

        $keyboard = [];
        $activeMethods = $user->paymentMethods()->pluck('name', 'id');

        foreach (PaymentMethod::all() as $pm) {
            $active = '';
            if (isset($activeMethods[$pm->id])) {
                $active = ' ☑️';
            }

            $keyboard[] = [Keyboard::inlineButton([
                'text' => $pm->name . ' ' . $active,
                'callback_data' => 'sell_set_' . $pm->id
            ])];
        }

        $reply_markup = new Keyboard([
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'inline_keyboard' => $keyboard
        ]);

        $this->replyWithMessage([
            'text' => __('bot.buy_settings.setup_data'),
            'reply_markup' => $reply_markup
        ]);

        // Цена монет

        $keyboard = [
            [Keyboard::inlineButton([
                'text' => __('bot.common.buttons.edit'),
                'callback_data' => 'price_change'
            ])]
        ];

        $reply_markup = new Keyboard([
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'inline_keyboard' => $keyboard
        ]);

        $this->replyWithMessage([
            'text' => __('bot.buy_settings.sell_price', ['price' => $user->token_price]),
            'reply_markup' => $reply_markup
        ]);

        // Вкл / выкл предложение

        $keyboard = [
            [Keyboard::inlineButton([
                'text' => __('bot.common.buttons.disable'),
                'callback_data' => 'sell_disable'
            ])]
        ];

        if (!$user->offer_active) {
            $keyboard = [
                [Keyboard::inlineButton([
                    'text' => __('bot.common.buttons.enable'),
                    'callback_data' => 'sell_enable'
                ])]
            ];
        }

        $reply_markup = new Keyboard([
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'inline_keyboard' => $keyboard
        ]);

        $this->replyWithMessage([
            'text' => $user->offer_active
                ? __('bot.buy_settings.ad_enabled') . ' ☑'
                : __('bot.buy_settings.ad_disabled'),
            'reply_markup' => $reply_markup
        ]);
    }
}
