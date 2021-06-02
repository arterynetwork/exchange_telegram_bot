<?php


namespace App\Bot;

use App\Models\PaymentMethod;
use App\Models\User;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;
use App\Classes\ArtrNode;


class SellSettingsCommand extends Command
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

        if (!$user->balance) {
            $this->replyWithMessage(['text' => __('bot.buy_settings.no_funds')]);
            return;
        }

        // Payment methods
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
            'text' => __('bot.sell_settings.configure_methods'),
            'reply_markup' => $reply_markup
        ]);

        // Coins price

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

        $price = round(config('artr.price') * (1 + $user->sell_p / 100), 2);
        $user->token_price = round(config('artr.price') * (1 + $user->sell_p / 100), 2);
        $user->save();

        $this->replyWithMessage([
            'text' => __('bot.sell_settings.price_info', [
                'percent' => $user->sell_p,
                'price' => ArtrNode::getMultiCourseStringByRub($price, true)
            ]),
            'reply_markup' => $reply_markup
        ]);

        // On / off

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
                ? __('bot.sell_settings.add_enabled') . ' ☑'
                : __('bot.sell_settings.add_disabled'),
            'reply_markup' => $reply_markup
        ]);
    }
}
