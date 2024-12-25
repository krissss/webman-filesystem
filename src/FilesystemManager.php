<?php

namespace WebmanTech\LaravelFilesystem;

use Illuminate\Filesystem\FilesystemManager as LaravelFilesystemManager;
use League\Flysystem\FilesystemInterface;
use WebmanTech\LaravelFilesystem\Extend\ExtendInterface;
use WebmanTech\LaravelFilesystem\Traits\ChangeAppUse;

class FilesystemManager extends LaravelFilesystemManager
{
    use ChangeAppUse;

    /**
     * @var array
     */
    protected $filesystemConfig = [];

    public function __construct()
    {
        $this->filesystemConfig = config('plugin.webman-tech.laravel-filesystem.filesystems', []);
        $this->customCreators = $this->filesystemConfig['extends'] ?? [];
        $autoExtends = collect($this->filesystemConfig['disks'])
            ->pluck('driver')
            ->unique()
            ->filter(function (string $driver) {
                return class_exists($driver) && is_a($driver, ExtendInterface::class, true);
            })
            ->mapWithKeys(function (string $driver) {
                return [$driver => $driver];
            })
            ->all();
        $this->customCreators = array_merge($autoExtends, $this->customCreators);
        parent::__construct(null);
    }

    /**
     * @inheritDoc
     */
    protected function adapt(FilesystemInterface $filesystem)
    {
        return FilesystemAdapter::wrapper(parent::adapt($filesystem));
    }

    /**
     * @inheritDoc
     */
    protected function callCustomCreator(array $config)
    {
        $adapter = (function($config) {
            $creator = $this->customCreators[$config['driver']];
            if (is_string($creator) && is_a($creator, ExtendInterface::class, true)) {
                $driver = $creator::createExtend($config);
                if ($driver instanceof FilesystemInterface && method_exists($this, 'adapt')) {
                    return $this->adapt($driver);
                }
                return $driver;
            }

            return parent::callCustomCreator($config);
        })($config);
        return FilesystemAdapter::wrapper($adapter);
    }
}
