<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GetCourse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get current ARTR courses for 4 currencies';

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
        print_r(\App\Classes\ArtrNode::getStaticCourse());
        return 0;
    }
}
