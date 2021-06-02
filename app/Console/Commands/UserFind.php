<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserFind extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:find {chatId} {--code=1 : Использовать код, вместо chatId}';

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
        if ($this->option('code')) {
            $chatId = User::invertCode($chatId);
        }

        $user = User::getUser($chatId);

        $this->info(print_r($user->toArray()));

        print_r(\Telegram::bot()->getChat(['chat_id' => $user->chat_id]));

        return 0;
    }
}
