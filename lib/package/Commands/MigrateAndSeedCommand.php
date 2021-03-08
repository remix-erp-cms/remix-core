<?php

namespace Package\Commands;

use Illuminate\Console\Command;

class MigrateAndSeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:plugin {--database=} {--path=} {--force} {--seed} {--class}';

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
     * @return mixed
     */
    public function handle()
    {
        //
        $this->call('migrate', [
            '--database'  => $this->option('database'),
            '--path'      => $this->option('path'),
            '--force'     => $this->option('force'),
        ]);

        $this->call('db:seed', [
            '--database'  => $this->option('database'),
            '--class'     => $this->option('class'),
            '--force'     => $this->option('force'),
        ]);
    }
}
