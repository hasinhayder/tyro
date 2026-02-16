<?php

namespace HasinHayder\Tyro\Console\Commands;

class StarCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:sys-star {--no-open : Only print the link instead of opening a browser}';

    protected $aliases = ['tyro:star'];

    protected $description = 'Open the Tyro GitHub repository so you can star it';

    public function handle(): int
    {
        $url = 'https://github.com/hasinhayder/tyro';

        if (! $this->option('no-open') && $this->openUrl($url)) {
            $this->info('Opening the Tyro repository in your default browser...');
        } else {
            $this->line('Give Tyro a ⭐ at: '.$url);
        }

        return self::SUCCESS;
    }
}
