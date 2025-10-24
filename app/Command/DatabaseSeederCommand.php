<?php

declare(strict_types=1);

namespace App\Command;

use App\Seeder\AdminUserSeeder;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Database Seeder Command
 * 
 * Runs database seeders
 * 
 * Usage: php bin/hyperf.php db:seed
 */
#[Command]
class DatabaseSeederCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('db:seed');
    }

    protected function configure()
    {
        parent::configure();
        $this->setDescription('Run database seeders');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->line('Running database seeders...');

        try {
            // Run admin user seeder
            $seeder = new AdminUserSeeder();
            $seeder->run();

            $this->info('Database seeders completed successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Database seeding failed: ' . $e->getMessage());
            return 1;
        }
    }
}
