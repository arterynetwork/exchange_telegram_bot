<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Classes\ArtrNode;
use Illuminate\Console\Command;

class UserAddCoins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:add_coins {chatId} {amount} {--code=1 : Подтверждение не по чат ID а по коду на пополнение}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $chatId = $this->argument('chatId');
        $amount = $this->argument('amount');

        if ($this->option('code')) {
            $chatId = User::invertCode($chatId);
        }

        $user = User::getUser($chatId);

        if (!$user) {
            $this->error('Пользователь с chatId ' . $chatId . ' не найден');
            return 0;
        }

        $amount = $amount * 1000000;

        if (!$this->confirm("Перевести " . ArtrNode::formatAmount($amount)
            . " ARTR пользователю {$user->chat_id} {$user->name}")) {
            return 0;
        }

        User::whereChatId($user->chat_id)->increment('balance', $amount);

        return 0;
    }
}
