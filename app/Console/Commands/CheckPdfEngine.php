<?php

namespace App\Console\Commands;

use Fagus\Laravel\Facades\Fagus;
use Illuminate\Console\Command;
use Throwable;

/**
 * Renders a tiny PDF to prove the Fagus binary can run on this server. The deploy runs it
 * before switching releases, so a host that cannot start the binary keeps the old release.
 */
class CheckPdfEngine extends Command
{
    protected $signature = 'pdf:check';

    protected $description = 'Check that the PDF engine (Fagus) can render on this server';

    public function handle(): int
    {
        try {
            $bytes = Fagus::html('<!DOCTYPE html><html lang="et"><body><p>Droppie PDF check: õäöü</p></body></html>')
                ->render()
                ->bytes();
        } catch (Throwable $exception) {
            $this->components->error('The PDF engine could not render: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! str_starts_with($bytes, '%PDF-')) {
            $this->components->error('The PDF engine returned something that is not a PDF.');

            return self::FAILURE;
        }

        $this->components->info('PDF engine works (Fagus '.Fagus::engine()->version().', '.strlen($bytes).' bytes).');

        return self::SUCCESS;
    }
}
