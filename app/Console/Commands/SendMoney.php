<?php

namespace App\Console\Commands;

use App\Models\Send;
use Illuminate\Console\Command;

class SendMoney extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coins:send {address} {amount}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send coins from bot to specific wallet';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $address = $this->argument('address');
        $amount = $this->argument('amount');

        $s = new Send();
        $s->address = $address;
        $s->amount = $amount * 1000000;
        $s->status = 0;
        $s->save();

        return 0;
    }
}
