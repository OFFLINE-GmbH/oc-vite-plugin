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

    protected Collection $manifestCache;

    protected bool $devServerIncluded = false;

    protected bool $initialized = false;

    protected array $devEnvs;

    protected const EXT_JS = ['js', 'js', 'jsx', 'ts', 'tsx'];
    protected const EXT_CSS = ['css'];

    protected string $viteHost;
    protected string $outDir;

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
        $this->devEnvs = $devEnvs;
        $this->viteHost = $viteHost;

        if (in_array(App::environment(), $devEnvs, true)) {
            $this->includeDevServer($viteHost, $includes);
        } else {
            $this->includeManifest($manifestFileName, $includes);
        }

        $this->initialized = true;
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

        $this->outDir = $this->extractOutDir($manifestPath, $manifestFileName);

        $this->getManifest($manifestPath)->filter(
            fn ($value, $name) => in_array($name, $includes, true)
        )->each(
            fn ($asset) => $this->includeManifestAsset($asset)
        );
    }

    /**
     * Include an asset from the manifest.json file.
     */
    protected function includeManifestAsset(object $asset)
    {
        if (Str::endsWith($asset->file, self::EXT_CSS)) {
            $this->controller->addCss("{$this->outDir}/{$asset->file}");
        } else {
            $this->controller->addJs("{$this->outDir}/{$asset->file}", ['type' => 'module']);
        }

        foreach (array_wrap($asset->css ?? []) as $css) {
            $this->controller->addCss("{$this->outDir}/{$css}");
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
            if (Str::endsWith($include, self::EXT_JS)) {
                $this->controller->addJs("{$viteHost}/${include}", ['type' => 'module']);
            } else {
                $this->controller->addCss("{$viteHost}/${include}");
            }
        }
    }

    /**
     * Include an asset directly.
     */
    public function resolveAsset(string $asset)
    {
        if (!$this->initialized) {
            throw new RuntimeException('[OFFLINE.Vite] Vite is not yet initialized. Call Vite::init() or {{ vite() }} with your configuration.');
        }

        if (in_array(App::environment(), $this->devEnvs, true)) {
            return $this->resolveAssetDev($asset)->viaDev();
        }

        return $this->resolveAssetProd($asset)->viaProd();
    }

    /**
     * Add an asset path from the vite dev server.
     */
    protected function resolveAssetDev(string $asset)
    {
        if (Str::endsWith($asset, self::EXT_JS)) {
            return Asset::make("{$this->viteHost}/${asset}", ['type' => 'module']);
        }

        return Asset::make("{$this->viteHost}/${asset}");
    }

    /**
     * Resolve an asset path from the manifest.json file.
     */
    protected function resolveAssetProd(string $assetPath)
    {
        $asset = $this->getManifest()->get($assetPath);
        if (!$asset) {
            throw new RuntimeException(sprintf('[OFFLINE.Vite] Failed to find asset %s in manifest.json', $assetPath));
        }

        if (Str::endsWith($asset->src, self::EXT_CSS)) {
            return Asset::make("{$this->outDir}/{$asset->file}");
        }

        // JS modules may contain additional CSS files.
        $this->controller->addJs("{$this->outDir}/{$asset->file}", ['type' => 'module']);

        $css = [];
        foreach (array_wrap($asset->css ?? []) as $file) {
            $css[] = "{$this->outDir}/{$file}";
        }

        return Asset::make("{$this->outDir}/{$asset->file}", ['type' => 'module'], $css);
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
    protected function getManifest(?string $manifestPath = null): Collection
    {
        if (isset($this->manifestCache)) {
            return $this->manifestCache;
        }

        if (!$manifestPath) {
            throw new RuntimeException('[OFFLINE.Vite] Missing manifest path.');
        }

        return $this->manifestCache = collect(
            json_decode(file_get_contents($manifestPath), false, 512, JSON_THROW_ON_ERROR)
        );
    }
}
