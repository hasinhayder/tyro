<?php

namespace HasinHayder\Tyro\Console\Commands;

class PostmanCollectionCommand extends BaseTyroCommand {
    protected $signature = 'tyro:postman-collection {--no-open : Only print the Postman collection URL}';

    protected $description = 'Open the Tyro Postman collection in your browser';

    private const COLLECTION_URL = 'https://github.com/hasinhayder/tyro/blob/main/Tyro.postman_collection.json';

    public function handle(): int {
        if (!$this->option('no-open') && $this->openUrl(self::COLLECTION_URL)) {
            $this->info('Opening the Tyro Postman collection...');
        } else {
            $this->line('Postman collection: ' . self::COLLECTION_URL);
        }

        return self::SUCCESS;
    }
}
