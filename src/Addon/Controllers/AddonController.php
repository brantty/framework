<?php
/**
 * This file is part of Notadd.
 *
 * @author TwilRoad <heshudong@ibenchu.com>
 * @copyright (c) 2017, notadd.com
 * @datetime 2017-02-22 17:50
 */
namespace Notadd\Foundation\Addon\Controllers;

use Closure;
use Illuminate\Support\Collection;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\MountManager;
use Notadd\Foundation\Addon\Addon;
use Notadd\Foundation\Addon\AddonManager;
use Notadd\Foundation\Routing\Abstracts\Controller;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Class ExtensionController.
 */
class AddonController extends Controller
{
    /**
     * @var \Notadd\Foundation\Addon\AddonManager
     */
    protected $manager;

    /**
     * AddonController constructor.
     *
     * @param \Notadd\Foundation\Addon\AddonManager $manager
     */
    public function __construct(AddonManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function install()
    {
        set_time_limit(0);
        $addon = $this->manager->get($this->request->input('identification'));
        $output = new BufferedOutput();
        $result = false;
        $this->db->beginTransaction();
        if ($addon) {
            $collection = collect();
            // Has Migration.
            $addon->offsetExists('migrations') && $collection->put('migrations', $addon->get('migrations'));
            // Has Publishes.
            $addon->offsetExists('publishes') && $collection->put('publishes', $addon->get('publishes'));
            if (!$this->setting->get('addon.' . $addon->identification() . '.installed', false)) {
                if ($collection->count() && $collection->every(function ($instance, $key) use ($addon, $output) {
                        switch ($key) {
                            case 'migrations':
                                if (is_array($instance) && collect($instance)->every(function ($path) use (
                                        $addon,
                                        $output
                                    ) {
                                        $path = $addon->get('directory') . DIRECTORY_SEPARATOR . $path;
                                        $migration = str_replace($this->container->basePath(), '', $path);
                                        $migration = trim($migration, DIRECTORY_SEPARATOR);
                                        $input = new ArrayInput([
                                            '--path'  => $migration,
                                            '--force' => true,
                                        ]);
                                        $this->getConsole()->find('migrate')->run($input, $output);

                                        return true;
                                    })) {
                                    return true;
                                } else {
                                    return false;
                                }
                                break;
                            case 'publishes':
                                if (is_array($instance) && collect($instance)->every(function ($from, $to) use (
                                        $addon,
                                        $output
                                    ) {
                                        $from = $addon->get('directory') . DIRECTORY_SEPARATOR . $from;
                                        $to = $this->container->basePath() . DIRECTORY_SEPARATOR . 'statics' . DIRECTORY_SEPARATOR . $to;
                                        if ($this->file->isFile($from)) {
                                            $this->publishFile($from, $to);
                                        } else {
                                            if ($this->file->isDirectory($from)) {
                                                $this->publishDirectory($from, $to);
                                            }
                                        }

                                        return true;
                                    })) {
                                    return true;
                                } else {
                                    return false;
                                }
                                break;
                            default:
                                return false;
                                break;
                        }
                    })) {
                    $result = true;
                }
            }
        }
        if ($result) {
            $this->container->make('log')->info('Install Addon ' . $this->request->input('identification') . ':',
                explode(PHP_EOL, $output->fetch()));
            $this->setting->set('addon.' . $addon->identification() . '.installed', true);
            $this->db->commit();

            return $this->response->json([
                'message' => '安装模块成功！',
            ])->setStatusCode(200);
        } else {
            $this->db->rollBack();

            return $this->response->json([
                'message' => '安装模块失败！',
            ])->setStatusCode(500);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function list()
    {
        $addons = $this->manager->repository();
        $enabled = $this->manager->repository()->enabled();
        $installed = $this->manager->repository()->installed();
        $notInstalled = $this->manager->repository()->notInstalled();

        return $this->response->json([
            'data'     => [
                'enabled'    => $this->info($enabled),
                'addons' => $this->info($addons),
                'installed'  => $this->info($installed),
                'notInstall' => $this->info($notInstalled),
            ],
            'messages' => '获取插件列表成功！',
        ]);
    }

    /**
     * Info list.
     *
     * @param \Illuminate\Support\Collection $list
     *
     * @return array
     */
    protected function info(Collection $list)
    {
        $data = collect();
        $list->each(function (Addon $extension) use ($data) {
            $data->put($extension->identification(), [
                'author'         => collect($extension->offsetGet('author'))->implode(','),
                'enabled'        => $extension->enabled(),
                'description'    => $extension->offsetGet('description'),
                'identification' => $extension->identification(),
                'name'           => $extension->offsetGet('name'),
            ]);
        });

        return $data->toArray();
    }

    /**
     * Publish the file to the given path.
     *
     * @param string $from
     * @param string $to
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function publishFile($from, $to)
    {
        $this->createParentDirectory(dirname($to));
        $this->file->copy($from, $to);
    }

    /**
     * Create the directory to house the published files if needed.
     *
     * @param $directory
     */
    protected function createParentDirectory($directory)
    {
        if (!$this->file->isDirectory($directory)) {
            $this->file->makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Publish the directory to the given directory.
     *
     * @param $from
     * @param $to
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function publishDirectory($from, $to)
    {
        $manager = new MountManager([
            'from' => new Flysystem(new LocalAdapter($from)),
            'to'   => new Flysystem(new LocalAdapter($to)),
        ]);
        foreach ($manager->listContents('from://', true) as $file) {
            if ($file['type'] === 'file') {
                $manager->put('to://' . $file['path'], $manager->read('from://' . $file['path']));
            }
        }
    }
}