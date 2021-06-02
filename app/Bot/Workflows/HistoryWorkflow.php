<?php


namespace App\Bot\Workflows;


use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update as UpdateObject;

class HistoryWorkflow extends BaseWorkflow
{

    public function processCallbackQuery($query, UpdateObject $update)
    {
        switch ($query) {
            case 'buy_history':
                $this->bot->triggerCommand('buy_history', $update);
                return true;
            case 'sell_history':
                Telegram::bot()->triggerCommand('sell_history', $update);
                return true;
            case 'full_buy_history':
                Telegram::bot()->triggerCommand('full_buy_history', $update);
                return true;
            case 'full_sell_history':
                Telegram::bot()->triggerCommand('full_sell_history', $update);
                return true;
        }

        return false;
    }
}
