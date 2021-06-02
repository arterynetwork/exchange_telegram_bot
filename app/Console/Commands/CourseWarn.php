<?php

namespace App\Console\Commands;

use App\Classes\ArtrNode;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Telegram\Bot\Laravel\Facades\Telegram;

class CourseWarn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:warn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warn users about course change';

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
        $minPrice = round(1000000 / $course['rub'] * 1.05, 2);

        User::whereOfferActive(true)
            ->chunk(200, function ($users) use ($minPrice) {
                /** @var User $user */
                foreach ($users as $user) {
                    if ($user->token_price < $minPrice) {
                        $this->info($user->token_price . ' ' . $minPrice);
                        try {
                            Telegram::bot()->sendMessage([
                                'chat_id' => $user->chat_id,
                                'text' => __('bot.console.course_changed')
                            ]);
                        } catch (\Throwable $er) {
                            $this->error('Send error');
                        }
                    }
                }
            });
        return 0;
    }
}
