<?php

namespace Tests\Commands\Upgrade;

use File;
use Illuminate\Support\Str;

abstract class TestCase extends \Tests\Commands\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $tables = $this->app['db']->connection()->getDoctrineSchemaManager()->listTableNames();

        foreach ($tables as $table) {
            $this->app['db']->statement("DROP TABLE $table");
        }

        preg_match('/V(\d+_){3}\d+/', static::class, $version);

        // Migration
        $this->app['migration.repository']->createRepository();
        $migrator = app('migrator');
        $files    = $migrator->getMigrationFiles(base_path('tests/Commands/Upgrade/' . $version[0] . '/migrations'));

        foreach ($files as $file) {
            $className     = Str::studly(implode('_', array_slice(explode('_', $migrator->getMigrationName($file)), 4)));
            $migrationFQCN = '\\Tests\\Commands\\Upgrade\\' . $version[0] . '\\Migrations\\' . $className;
            (new $migrationFQCN())->up();
        }

        // Seed
        $file = collect(File::files(base_path('tests/Commands/Upgrade/' . $version[0] . '/seeds')))
            ->transform(function ($v) {
                return [
                    'cTime'    => $v->getCTime(),
                    'filename' => basename($v->getFilename(), '.php'),
                ];
            })
            ->filter(function ($v) {
                return $v['filename'] === 'DatabaseSeeder' ? false : true;
            })
            ->sortBy('cTime')
            ->pluck('filename');

        foreach ($file as $k => $v) {
            $seedFQCN = '\\Tests\\Commands\\Upgrade\\' . $version[0] . '\\Seeds\\' . $v;
            (new $seedFQCN())->run();
        }
    }
}
