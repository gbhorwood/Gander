<?php
namespace Gbhorwood\Gander;

use Illuminate\Console\Command;

/**
 * Models
 */
use Gbhorwood\Gander\Models\GanderStack;
use Gbhorwood\Gander\Models\GanderApiKey;
use Gbhorwood\Gander\Models\GanderRequest;

/**
 * Gander artisan console command
 *
 */
class GanderConsole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbhorwood:gander
        {--create-client : Creates a client html page for viewing Gander data.}
        {--list-keys : List all api keys.}
        {--delete-key= : Delete one key by name.}
        {--key-name= : The name of the api access key used by the client. Overrides default.}
        {--outfile= : The name of the client file. Overrides default.}';

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
    public function handle():Int
    {
        if($this->option('create-client')) {
            return $this->createClient();
        }

        else if($this->option('list-keys')) {
            return $this->listKeys();
        }

        else if($this->option('delete-key')) {
            return $this->deleteKey();
        }

        $this->line("Pass one of --createClient --list-keys or --delete-key");

        /**
         * Return zero for success
         */
        return 0;
    }

    /**
     * Create and export an html client page for this api
     *
     * @return Int
     */
    protected function createClient():Int
    {
        $apiDomain = trim(env('APP_URL'));

        /**
         * Poll user that APP_URL is the correct domain
         * Return non-zero for error
         */
        if(!$this->confirm($apiDomain.PHP_EOL."is this the correct api domain?", true)) {
            $this->line('Please adjust the APP_URL in your .env file to the correct domain');
            $this->error('exiting');
            return 1;
        }

        /**
         * Generate and save the key
         */
        do {
            $apiKey = $this->generateKey();
            $apiKeyName = $this->getKeyName();
            $keyWritten = $this->writeApiKey($apiKeyName, $apiKey);
        }
        while(!$keyWritten);

        /**
         * Get the name of the output file for the client
         */
        $outfilePath = $this->getOutfileName($apiDomain, $apiKeyName);

        /**
         * Preflight ability to write client file to disk
         * Return non-zero for error
         */
        if(!$this->preflightFileWrite($outfilePath)) {
            $this->deleteApiKey($apiKeyName);
            return 1;
        }

        /**
         * Write client to disk
         */
        $this->writeClient($apiDomain, $apiKeyName, $apiKey, $outfilePath);

        $this->info('client created:');
        $this->line($outfilePath);

        /**
         * Return zero for success
         */
        return 0;
    }

    /**
     * Output all key names as table
     *
     * @return Int
     */
    protected function listKeys():Int
    {
        $keys = GanderApiKey::all()->toArray();

        /**
         * Output table of keys
         */
        $pad = max(array_map(fn($n) => strlen($n['name']), $keys));
        $this->info('current api keys');
        fwrite(STDOUT, str_pad('name', $pad, ' ').' | created at'.PHP_EOL);
        fwrite(STDOUT, join('', array_fill(0, $pad + 1, '-')).'+'.join('', array_fill(0, 20, '-')).PHP_EOL);
        array_map(fn($n) => fwrite(STDOUT, str_pad($n['name'], $pad, ' ', STR_PAD_RIGHT).' | '.date('Y-m-d H:i:s', strtotime($n['created_at'])).PHP_EOL), $keys);
        
        /**
         * Return zero for success
         */
        return 0;
    }

    /**
     * Deletes one key by name
     *
     * @return Int
     */
    protected function deleteKey():Int
    {
        $apiKeyName = $this->option('delete-key');

        try {
            GanderApiKey::where('name', '=', $apiKeyName)->delete();
            $this->info("Key $apiKeyName deleted");
        }
        catch (\Exception $e) {
            $this->info("Could not delete key $apiKeyName");
        }

        /**
         * Return zero for success
         */
        return 0;
    }

    /**
     * Read client template, do substitutions and write to disk at $outfilePath
     *
     * @param  String $apiDomain
     * @param  String $apiKeyName
     * @param  String $apiKey
     * @param  String $outfilePath
     * @return void
     */
    protected function writeClient(String $apiDomain, String $apiKeyName, String $apiKey, String $outfilePath):void
    {
        $clientTemplateHtml = file_get_contents(dirname(__FILE__).DIRECTORY_SEPARATOR.'client_template.html');
        $clientTemplateHtml = str_replace(
            ['!!apiDomain!!', '!!apiKey!!', '!!apiKeyName!!'],
            [$apiDomain, $apiKey, $apiKeyName],
            $clientTemplateHtml);

        $fp = fopen($outfilePath, 'w');
        fwrite($fp, $clientTemplateHtml);
        fclose($fp);
    }

    /**
     * Validate that the client file can be written to disk.
     *
     * @return bool True if can be written to disk
     */
    private function preflightFileWrite(String $path):bool
    {
        // directory exists
        if(!file_exists(dirname($path))) {
            $this->error('The directory for the client outfile does not exist');
            return false;
        }

        // directory writeable
        if(!is_writable(dirname($path))) {
            $this->error('Insufficient permissions to write the client outfile');
            return false;
        }

        // no clobber
        if(file_exists($path)) {
            $this->error('A client outfile at this location already exists');
            return false;
        }
        
        return true;
    }

    /**
     * Write the api key/name to the database. 
     * If there is a duplicate generated key name, return false so we can try again
     * until uniqueness is required. If the name is user-supplied and duplicate, return
     * true and do not write so we can re-use the existing key.
     *
     * @return bool
     */
    private function writeApiKey(String $apiKeyName, String $apiKey):bool
    {
        $isCustom = $this->option('key-name') == null ? false : true;
        $nameExists = (bool)GanderApiKey::where('name', '=', $apiKeyName)->count();

        if ($isCustom && $nameExists) {
            return true;
        }

        if (!$isCustom && $nameExists) {
            return false;
        }

        $ganderApiKey = new GanderApiKey();
        $ganderApiKey->name = $apiKeyName;
        $ganderApiKey->key = $apiKey;
        $ganderApiKey->save();

        return true;
    }

    /**
     * Deletes an api key that has a generated name. Used to 
     * clean up in the event of an error in createClient.
     *
     * @param  String $apiKeyName
     * @return void
     */
    private function deleteApiKey(String $apiKeyName):void
    {
        $isCustom = $this->option('key-name') == null ? false : true;
        if(!$isCustom) {
            GanderApiKey::where('name', '=', $apiKeyName)->delete();
        }
    }

    /**
     * Create and return the out file name; either the user-supplied one from the --outfile
     * option, if set, or a generated one.
     *
     * @return String
     */
    private function getOutfileName(String $apiDomain, String $apiKeyName):String
    {
        /**
         * If user-supplied outfile path not supplied, generate path
         */
        $outfile = $this->option('outfile') ? trim($this->option('outfile')) : getcwd().DIRECTORY_SEPARATOR."gander_".parse_url($apiDomain)['host']."_".$apiKeyName.".html";

        /**
         * Function to expand ~ to home directory as bash would
         */
        $expandTilde = function($path) {
            return $path[0] == '~' ? posix_getpwuid(posix_getuid())['dir'].substr($path,1) : $path;
        };

        /**
         * Function to get absolute path of file
         */
        $makeRealPath = function($path) {
            return realpath(dirname($path)).DIRECTORY_SEPARATOR.basename($path);
        };

        return $makeRealPath($expandTilde($outfile));
    }

    /**
     * Get the name of the key; either the user-supplied one from the --key-name
     * option, if set, or a generated one.
     *
     * @return String
     */
    private function getKeyName():String
    {
        return $this->option('key-name') ?? $this->generateKeyName();
    }

    /**
     * Generate a 32 char api key.
     *
     * @return String
     */
    private function generateKey():String
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate a somewhat-unique and somewhat-readable name for the api key
     *
     * @return String
     */
    private function generateKeyName():String
    {
        $adjectives = [
            'Ancient',
            'Gnarled',
            'Forgotten',
            'Hollow',
            'Leafy',
            'Lonesome',
            'Lush',
            'Majestic',
            'Noble',
            'Powerful',
            'Serene',
            'Shady',
            'Stunted',
            'Towering',
            'Vibrant',
            'Whispering',
        ];

        $nouns = [
            'Alder',
            'Arbutus',
            'Aspen',
            'Birch',
            'Cedar',
            'Elm',
            'Fir',
            'Maple',
            'Oak',
            'Pine',
            'Poplar',
            'Sequoia',
            'Spruce',
            'Sycamore',
            'Willow',
        ];

        return $adjectives[rand(1,count($adjectives)-1)].$nouns[rand(1,count($nouns)-1)].rand(10,99).range('a', 'z')[rand(0,25)];
    }
}
