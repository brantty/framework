<?php
/**
 * This file is part of Notadd.
 * @author TwilRoad <269044570@qq.com>
 * @copyright (c) 2016, iBenchu.org
 * @datetime 2016-10-21 11:04
 */
namespace Notadd\Foundation\Bootstrap;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
/**
 * Class LoadConfiguration
 * @package Notadd\Foundation\Bootstrap
 */
class LoadConfiguration {
    /**
     * @param \Illuminate\Contracts\Foundation\Application|\Notadd\Foundation\Application $app
     * @return void
     */
    public function bootstrap(Application $app) {
        $items = [];
        if(file_exists($cached = $app->getCachedConfigPath())) {
            $items = require $cached;
            $loadedFromCache = true;
        }
        $app->instance('config', $config = new Repository($items));
        if(!isset($loadedFromCache)) {
            $this->loadConfigurationFiles($app, $config);
        }
        $app->detectEnvironment(function () use ($config) {
            return $config->get('app.env', 'production');
        });
        date_default_timezone_set($config['app.timezone']);
        mb_internal_encoding('UTF-8');
    }
    /**
     * @param \Illuminate\Contracts\Foundation\Application|\Notadd\Foundation\Application $app
     * @param \Illuminate\Contracts\Config\Repository $repository
     * @return void
     */
    protected function loadConfigurationFiles(Application $app, RepositoryContract $repository) {
        foreach($this->getConfigurationFiles($app) as $key => $path) {
            $repository->set($key, require $path);
        }
    }
    /**
     * @param \Illuminate\Contracts\Foundation\Application|\Notadd\Foundation\Application $app
     * @return array
     */
    protected function getConfigurationFiles(Application $app) {
        $files = [];
        $configPath = realpath($app->configPath());
        foreach(Finder::create()->files()->name('*.php')->in($configPath) as $file) {
            $nesting = $this->getConfigurationNesting($file, $configPath);
            $files[$nesting . basename($file->getRealPath(), '.php')] = $file->getRealPath();
        }
        return $files;
    }
    /**
     * @param \Symfony\Component\Finder\SplFileInfo $file
     * @param string $configPath
     * @return string
     */
    protected function getConfigurationNesting(SplFileInfo $file, $configPath) {
        $directory = dirname($file->getRealPath());
        if($tree = trim(str_replace($configPath, '', $directory), DIRECTORY_SEPARATOR)) {
            $tree = str_replace(DIRECTORY_SEPARATOR, '.', $tree) . '.';
        }
        return $tree;
    }
}