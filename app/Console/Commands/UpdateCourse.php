<?php

namespace App\Console\Commands;

use App\Classes\ArtrNode;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateCourse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:course {--loop=0}';

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
        $loop = $this->option('loop');

        do {
            $course = ArtrNode::getStaticCourse();
            $price = round(1000000 / $course['rub'], 2);
            User::where('offer_active', true)->update([
                'token_price' => DB::raw("round($price * (1 + sell_p / 100), 2)")
            ]);

            User::where('buy_offer', true)->update([
                'buy_price' => DB::raw("round($price * (1 + buy_p / 100), 2)")
            ]);

            if ($loop) {
                sleep(40);
            }
        } while ($loop);

        return 0;
    }
}
