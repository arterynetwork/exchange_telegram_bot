<?php


namespace App\Classes\ChainTransactions;


use App\Models\Transaction;
use App\Classes\ArtrNode;

class Common
{
    public $tx;

    public function __construct($tx)
    {
        $this->tx = $tx;
    }

    public function getType()
    {
        return $this->tx['tx']['value']['msg'][0]['type'];
    }

    public function getHash()
    {
        return $this->tx['txhash'];
    }

    public function getStatus()
    {
        return !empty($this->tx['code'])
            ? Transaction::STATUS_ERROR
            : Transaction::STATUS_SUCCESS;
    }

    public function getSender()
    {
        return $this->tx['tx']['value']['msg'][0]['value']['from_address'] ??
            ($this->tx['tx']['value']['msg'][0]['value']['address'] ?? 'n/d');
    }

    public function getRecipient()
    {
        return ($this->tx['tx']['value']['msg'][0]['value']['to_address'] ??
            ($this->tx['tx']['value']['msg'][0]['value']['new_account'] ?? 'n/d'));
    }

    public function getDate()
    {
        return ArtrNode::getDate($this->tx['timestamp']);
    }

    public function getFee()
    {
        $fee = $this->tx['tx']['value']['fee']['amount'][0]["amount"] ?? 0;

        if ($fee == 0) {
            if (ArtrNode::hasEvents($this->tx, 'transfer')) {
                $r = ArtrNode::parseTransferEvent($this->tx);
                if (isset($r[config('artr.fee_collector')])) {
                    return $r[config('artr.fee_collector')];
                }
            }
        }

        return 0;
    }

    public function getAmount()
    {
        return 0;
    }

    public function getRecipients()
    {
        return [$this->getRecipient()];
    }

    public function getAmounts()
    {
        return [0];
    }

    public function getEvents()
    {
        return [];
    }

    public function processEvents()
    {
    }

    public function getMemo()
    {
        return $this->tx['tx']['value']['memo'] ?? '';
    }

    public function getTransaction()
    {
        $tx = new Transaction();
        $tx->hash = $this->getHash();
        $tx->status = $this->getStatus();
        $tx->sender = $this->getSender();
        $tx->recipient = $this->getRecipient();
        $tx->created_at = $this->getDate();
        $tx->data = $this->tx;
        $tx->fee = $this->getFee();
        $tx->amount = $this->getAmount();

        return $tx;
    }
}
