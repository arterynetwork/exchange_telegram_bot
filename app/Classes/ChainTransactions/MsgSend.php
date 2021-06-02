<?php


namespace App\Classes\ChainTransactions;

class MsgSend extends Common
{
    public function getAmount()
    {
        return $this->tx['tx']['value']['msg'][0]['value']['amount'][0]["amount"];
    }

    public function processEvents()
    {
    }
}
