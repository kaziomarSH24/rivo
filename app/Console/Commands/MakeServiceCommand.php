<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service class';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get the raw name from the argument
        $name = $this->argument('name');

        // Normalize backslashes to forward slashes for path creation
        $pathName = str_replace('\\', '/', $name);
        $path = $this->getPath($pathName);

        if ($this->files->exists($path)) {
            $this->error('Service already exists!');
            return false;
        }

        $this->makeDirectory($path);

        // For namespace generation, we need backslashes
        $namespaceName = str_replace('/', '\\', $pathName);
        $this->files->put($path, $this->buildClass($namespaceName));

        $this->info('Service created successfully.');
    }

    /**
     * Get the full path to the service class.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        // Replace forward slashes with the system's directory separator
        $name = str_replace('\\', '/', $name);
        return app_path("Services/{$name}.php");
    }

    /**
     * Create the directory for the class if it doesn't exist.
     *
     * @param  string  $path
     * @return void
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }
    }

    /**
     * Get the namespace for the class.
     *
     * @param  string  $name
     * @return string
     */
    protected function getNamespace($name)
    {
        // Start with the base namespace
        $namespace = 'App\\Services';

        // Remove the class name part to get the sub-namespace
        $subNamespace = trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');

        // Append the sub-namespace if it exists
        if (!empty($subNamespace)) {
            $namespace .= '\\' . $subNamespace;
        }

        return $namespace;
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = $this->files->get(__DIR__.'/stubs/service.stub');

        $namespace = $this->getNamespace($name);
        $className = class_basename($name);
        $modelName = str_replace('Service', '', $className);

        // Replace placeholders in the stub
        $stub = str_replace('{{namespace}}', $namespace, $stub);
        $stub = str_replace('{{className}}', $className, $stub);
        $stub = str_replace('{{modelName}}', $modelName, $stub);

        return $stub;
    }
}
