<?php

namespace OFFLINE\Vite\Classes;

use Cms\Classes\Controller;
use Cms\Classes\Theme;
use Cms\Classes\ThemeManager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use October\Rain\Support\Str;
use October\Rain\Support\Traits\Singleton;

class Vite
{
    use Singleton;

    protected Controller $controller;
    protected Theme $theme;
    protected bool $devServerIncluded = false;
    public string $manifestPath;

    public function __construct()
    {
        $this->controller = Controller::getController() ?? new Controller();
        $this->theme = ThemeManager::instance()->getActiveTheme();
        $this->manifestPath = Config::get('offline.vite::config.manifest');

        if (!$this->manifestPath) {
            throw new \RuntimeException('[OFFLINE.Vite] Set the VITE_MANIFEST env variable to the path of your manifest.json file.');
        }
    }

    /**
     * {{ vite() }} Twig function.
     */
    public static function init(array $includes = [], array $args = []): void
    {
        // If no options are specified, use all passed in params as
        self::instance()->includeVite(
            $includes,
            array_get($args, 'manifestFileName', 'manifest.json'),
            array_wrap(array_get($args, 'devEnvs', ['local'])),
            array_get($args, 'host', 'http://localhost:5173'),
        );
    }

    /**
     * Include the Vite dev server in development mode.
     * Otherwise, include all entry points from the manifest.
     * @throws \JsonException
     */
    protected function includeVite(
        array $includes,
        string $manifestFileName = 'manifest.json',
        array $devEnvs = ['local'],
        string $viteHost = 'http://localhost:5173'
    ): void {
        if (in_array(App::environment(), $devEnvs, true)) {
            $this->includeDevServer($viteHost, $includes);
        } else {
            $this->includeManifest($manifestFileName, $includes);
        }
    }

    /**
     * Include all assets from the manifest.json file.
     */
    protected function includeManifest(string $manifestFileName, array $includes)
    {
        $manifestPath = $this->manifestPath;

        $hash = hash('sha1', $manifestPath . $manifestFileName . implode('__', $includes));

        $includeFiles = Cache::rememberForever("offline.vite.include_files.{$hash}", function () use ($manifestPath, $includes) {
            $manifestPath = $this->theme->getPath() . '/' . $manifestPath;
            if (!file_exists($manifestPath)) {
                throw new \RuntimeException('[OFFLINE.Vite] Specified manifest file does not exist: ' . $manifestPath);
            }

            $manifest = collect(json_decode(file_get_contents($manifestPath), false, 512, JSON_THROW_ON_ERROR));

            return $manifest->filter(fn ($value, $name) => in_array($name, $includes, true));
        });

        $outDir = $this->extractOutDir($manifestPath, $manifestFileName);

        $includeFiles->each(function ($asset) use ($outDir) {
            $this->includeManifestAsset($asset, $outDir);
        });
    }

    /**
     * Include an asset from the manifest.json file.
     */
    protected function includeManifestAsset(object $asset, string $outDir)
    {
        if (Str::endsWith($asset->file, '.css')) {
            $this->controller->addCss($outDir . '/' . $asset->file);
        } else {
            $this->controller->addJs($outDir . '/' . $asset->file, ['type' => 'module']);
        }

        foreach (array_wrap($asset->css ?? []) as $css) {
            $this->controller->addCss($outDir . '/' . $css);
        }
    }

    /**
     * Include the Vite dev server and all specified assets.
     */
    protected function includeDevServer(string $viteHost, array $includes)
    {
        if (!$this->devServerIncluded) {
            $this->controller->addJs("{$viteHost}/@vite/client", ['type' => 'module']);
            $this->devServerIncluded = true;
        }

        foreach ($includes as $include) {
            if (Str::endsWith($include, ['.ts', '.js', '.jsx', '.tsx'])) {
                $this->controller->addJs("{$viteHost}/${include}", ['type' => 'module']);
            } else {
                $this->controller->addCss("{$viteHost}/${include}");
            }
        }
    }

    /**
     * Extract the output dir from the manifest path.
     */
    protected function extractOutDir(string $manifestPath, string $manifestFileName): string
    {
        $outDir = Str::replaceLast($manifestFileName, '', $manifestPath);

        return trim(Str::replace($this->theme->getPath(), '', $outDir), '/');
    }
}
