<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Classes\ArtrNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArtrLoadBlocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blocks:load {--loop}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load blocks from blockchain (pooling mode)';

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
        $lastBlockId = \Cache::get('last_block_id', config('artr.start_block'));

        if ($lastBlockId < config('artr.start_block')) {
            $lastBlockId = config('artr.start_block');
        }

        do {
            $lastBlock = ArtrNode::queryMaxBlock()['block']['header']['height'] - 1;
            if ($lastBlockId <= $lastBlock) {
                $this->info('Processing block ' . $lastBlockId);
                ArtrNode::loadBlock($lastBlockId);
                $lastBlockId++;
                \Cache::set('last_block_id', $lastBlockId);
            } else {
                sleep(5);
            }
        } while ($loop);
    }
}
