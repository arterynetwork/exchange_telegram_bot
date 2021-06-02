<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LoadTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:load {fileName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load translations from CSV';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    var $translation = [];

    /**
     * Sets a value in a nested array based on path
     * See https://stackoverflow.com/a/9628276/419887
     *
     * @param array $array The array to modify
     * @param string $path The path in the array
     * @param mixed $value The value to set
     * @param string $delimiter The separator for the path
     * @return The previous value
     */
    function set_nested_array_value(&$array, $path, &$value, $delimiter = '/')
    {
        $pathParts = explode($delimiter, $path);

        $current = &$array;
        foreach ($pathParts as $key) {
            $current = &$current[$key];
        }

        $backup = $current;
        $current = $value;

        return $backup;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fileName = $this->argument('fileName');

        $f = fopen(base_path($fileName), 'r');
        while ($data = fgetcsv($f, 20000, ",")) {
            $this->set_nested_array_value($this->translation, $data[0], $data[2], '.');
        }

        file_put_contents(resource_path('lang/en/bot.php'),
            "<?php\n return " . var_export($this->translation['bot'], true) . ';');

        return 0;
    }
}
