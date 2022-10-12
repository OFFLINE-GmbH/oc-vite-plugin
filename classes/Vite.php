<?php

namespace OFFLINE\Vite\Classes;

use Cms\Classes\Controller;
use Cms\Classes\Theme;
use Cms\Classes\ThemeManager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use JsonException;
use October\Rain\Support\Arr;
use October\Rain\Support\Collection;
use October\Rain\Support\Facades\File;
use October\Rain\Support\Str;
use October\Rain\Support\Traits\Singleton;
use RuntimeException;

class Vite
{
    use Singleton;

    protected Theme $theme;

    protected string $manifestPath;

    protected string $manifestFilename = 'manifest.json';

    protected Collection $manifestCache;

    protected bool $devServerIncluded = false;

    protected bool $initialized = false;

    protected array $devEnvs;

    protected const EXT_JS = ['js', 'js', 'jsx', 'ts', 'tsx'];
    protected const EXT_CSS = ['css', 'styl', 'less', 'sass', 'scss'];

    protected string $viteHost;
    protected string $outDir;

    /**
     * Initialize the Vite plugin.
     */
    public function init(): void
    {
        $this->theme = ThemeManager::instance()->getActiveTheme();
        $this->manifestPath = Config::get('offline.vite::config.manifest');
        $this->manifestFilename = Config::get('offline.vite::config.manifest_filename');
        $this->devEnvs = Config::get('offline.vite::config.devEnvs');
        $this->viteHost = Config::get('offline.vite::config.host');
        $this->outDir = $this->extractOutDir($this->manifestPath, $this->manifestFilename);

        if (!$this->manifestPath) {
            throw new RuntimeException('[OFFLINE.Vite] Set the VITE_MANIFEST env variable to the path of your manifest.json file.');
        }

        $this->includeViteDevServer();
    }

    /**
     * Include the Vite dev server in development mode.
     */
    public function includeViteDevServer(): void
    {
        if ($this->isDevEnvironment()) {
            $this->includeDevServer();
        }

        $this->initialized = true;
    }

    /**
     * Include the Vite dev server and all specified assets.
     */
    protected function includeDevServer()
    {
        $controller = $this->getController();

        if (!$this->devServerIncluded) {
            $controller->addJs("{$this->viteHost}/@vite/client", ['type' => 'module']);
            $this->devServerIncluded = true;
        }
    }

    /**
     * Include an asset.
     * @param string|array{path: string, render?: boolean} $assets
     * @return string
     */
    public static function includeAssets($assets)
    {
        $assets = Arr::wrap($assets);

        $instance = self::instance();

        $output = [];

        foreach ($assets as $asset) {
            $asset = $instance->normalizeAssetInput($asset);

            $resolved = $instance->resolveAsset($asset);
            if (Arr::get($asset, 'render', true)) {
                $output[] = $resolved->render();
            } else {
                $resolved->include($instance->getController());
            }
        }

        return implode("\n", array_filter($output));
    }

    /**
     * Resolve an asset depending on the current env.
     */
    public function resolveAsset($asset)
    {
        $asset = $this->normalizeAssetInput($asset);

        if (!$this->initialized) {
            throw new RuntimeException('[OFFLINE.Vite] Vite is not yet initialized. Something is wrong.');
        }

        if ($this->isDevEnvironment()) {
            return $this->resolveAssetDev($asset)->viaDev();
        }

        return $this->resolveAssetProd($asset)->viaProd();
    }

    /**
     * Add an asset path from the vite dev server.
     */
    protected function resolveAssetDev(array $asset)
    {
        if (Str::endsWith($asset['path'], self::EXT_JS)) {
            return Asset::make("{$this->viteHost}/${asset['path']}", ['type' => 'module'] + $asset)->asJs();
        }

        return Asset::make("{$this->viteHost}/${asset['path']}", $asset)->asCss();
    }

    /**
     * Resolve an asset path from the manifest.json file.
     * @throws JsonException
     */
    protected function resolveAssetProd(array $assetInput)
    {
        $asset = $this->getManifest()->get($assetInput['path']);
        if (!$asset) {
            throw new RuntimeException(sprintf('[OFFLINE.Vite] Failed to find asset %s in manifest.json', $assetInput));
        }

        if (Str::endsWith($asset->src, self::EXT_CSS)) {
            return Asset::make("{$this->outDir}/{$asset->file}", $assetInput)->asCss();
        }

        $css = [];
        foreach (array_wrap($asset->css ?? []) as $file) {
            $css[] = "{$this->outDir}/{$file}";
        }

        return Asset::make("{$this->outDir}/{$asset->file}", ['type' => 'module'] + $assetInput, $css)->asJS();
    }

    /**
     * Read the manifest file from disk.
     * Cache for later access.
     */
    protected function getManifest(): Collection
    {
        if (isset($this->manifestCache)) {
            return $this->manifestCache;
        }

        if (!$this->manifestPath) {
            throw new RuntimeException('[OFFLINE.Vite] Missing manifest path.');
        }

        $path = $this->theme->getPath() . '/' . $this->manifestPath;
        if (!File::isLocalPath($path, true)) {
            throw new RuntimeException('[OFFLINE.Vite] Manifest path must be a local path inside your theme directory.');
        }

        return $this->manifestCache = collect(
            json_decode(file_get_contents($path), false, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Resolve a CMS controller.
     *
     * @return Controller
     */
    public function getController()
    {
        return Controller::getController() ?? new Controller();
    }

    /**
     * Extract the output dir from the manifest path.
     * Prepend the theme path.
     */
    protected function extractOutDir(string $manifestPath, string $manifestFileName): string
    {
        $outDir = Str::replaceLast($manifestFileName, '', $manifestPath);

        $clean = trim(Str::replace($this->theme->getPath(), '', $outDir), '/');

        return URL::to(sprintf('/themes/%s/%s', $this->theme->getDirName(), $clean));
    }

    protected function isDevEnvironment(): bool
    {
        return in_array(App::environment(), $this->devEnvs, true);
    }

    /**
     * Make sure the input is in the expected format.
     * @param $asset
     * @return array
     */
    private function normalizeAssetInput($asset): array
    {
        if (!is_array($asset)) {
            return ['path' => $asset];
        }

        if (!isset($asset['path'])) {
            throw new RuntimeException(sprintf('[OFFLINE.Vite] Missing path in asset input: %s', json_encode($asset)));
        }

        return $asset;
    }
}
