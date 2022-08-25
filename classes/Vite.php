<?php

namespace OFFLINE\Vite\Classes;

use Cms\Classes\Controller;
use Cms\Classes\Theme;
use Cms\Classes\ThemeManager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use JsonException;
use October\Rain\Support\Collection;
use October\Rain\Support\Str;
use October\Rain\Support\Traits\Singleton;
use RuntimeException;

class Vite
{
    use Singleton;

    protected Controller $controller;

    protected Theme $theme;

    protected string $manifestPath;

    protected array $manifestCache = [];

    protected bool $devServerIncluded = false;

    public function __construct()
    {
        $this->controller = Controller::getController() ?? new Controller();
        $this->theme = ThemeManager::instance()->getActiveTheme();
        $this->manifestPath = Config::get('offline.vite::config.manifest');

        if (!$this->manifestPath) {
            throw new RuntimeException('[OFFLINE.Vite] Set the VITE_MANIFEST env variable to the path of your manifest.json file.');
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
     * @throws JsonException
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
     * @throws JsonException
     */
    protected function includeManifest(string $manifestFileName, array $includes)
    {
        $manifestPath = $this->theme->getPath() . '/' . $this->manifestPath;

        if (!file_exists($manifestPath)) {
            throw new RuntimeException('[OFFLINE.Vite] Specified manifest file does not exist: ' . $manifestPath);
        }

        $this->getManifest($manifestPath)->filter(
            fn ($value, $name) => in_array($name, $includes, true)
        )->each(
            fn ($asset) => $this->includeManifestAsset($asset, $this->extractOutDir($manifestPath, $manifestFileName))
        );
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

    /**
     * Read the manifest file from disk.
     * Cache for later access.
     */
    protected function getManifest(string $manifestPath): Collection
    {
        if (isset($this->manifestCache[$manifestPath])) {
            return $this->manifestCache[$manifestPath];
        }

        return $this->manifestCache[$manifestPath] = collect(
            json_decode(file_get_contents($manifestPath), false, 512, JSON_THROW_ON_ERROR)
        );
    }
}
