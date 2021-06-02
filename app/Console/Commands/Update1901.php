<?php

namespace App\Console\Commands;

use App\Classes\ArtrNode;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Telegram\Bot\Laravel\Facades\Telegram;

class Update1901 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Update:1901';

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
        $course = ArtrNode::getStaticCourse();
        $price = round(1000000 / $course['rub'], 2);
        $price10 = round($price * 1.10, 2);

        $this->info('updating prices');
//        User::chunk(1000, function ($users) use ($price, $price10) {
//            /** @var User $user */
//            foreach ($users as $user) {
//                // Активно объявление о продаже
//                if ($user->buy_offer) {
//                    if (!$user->buy_expire) {
//                        $user->buy_expire = now()->addHours(12)->addMinutes(random_int(1, 10));
//                        $user->save();
//                    }
//                }
//            }
//        });

        $progress = $this->output->createProgressBar(User::count());

        User::chunk(1000, function ($users) use ($price, $price10, &$progress) {
            /** @var User $user */
            foreach ($users as $user) {
                try {
                    $progress->advance();
                    Telegram::bot()->sendMessage([
                        'chat_id' => $user->chat_id,
                        'text' => '*Изменения в правилах:*

В случае, если продавец принял вашу сделку, а затем написал с условием доплатить за монеты, обращайтесь в техническую поддержку. Продавец незамедлительно будет заблокирован, а сделка подтверждена автоматически.',
                        'parse_mode' => 'Markdown'
                    ]);
                    sleep(1);
                } catch (\Throwable $ex) {

                }
            }
        });

        $progress->finish();

        return 0;
    }
}
