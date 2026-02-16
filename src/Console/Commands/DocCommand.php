<?php

namespace HasinHayder\Tyro\Console\Commands;

class DocCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:sys-doc {--no-open : Only print the docs URL}';

    protected $aliases = ['tyro:doc'];

    protected $description = 'Open the Tyro documentation in your browser';

    public function handle(): int
    {
        $url = 'https://github.com/hasinhayder/tyro';

        if (! $this->option('no-open') && $this->openUrl($url)) {
            $this->info('Opening Tyro documentation...');
        } else {
            $this->line('Docs: '.$url);
        }

        return self::SUCCESS;
    }
}
