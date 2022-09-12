<?php

namespace OFFLINE\Vite;

use Cms\Classes\Controller;
use Cms\Classes\ThemeManager;
use October\Rain\Support\Facades\Event;
use OFFLINE\Vite\Classes\Asset;
use OFFLINE\Vite\Classes\Vite;
use System\Classes\PluginBase;

/**
 * Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Use this string to include a vite asset
     * via the addJs/addCss methods.
     */
    const VITE_ASSET_TOKEN = 'vite:';

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Vite',
            'description' => 'Integrate Vite in October CMS',
            'author' => 'OFFLINE',
            'icon' => 'icon-bolt',
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'vite' => [Vite::class, 'init'],
            ],
        ];
    }

    public function register()
    {
        // Replace the `vite:` token when adding JS/CSS assets.
        Event::listen('system.assets.beforeAddAsset', function (string $type, string &$path, array &$attributes) {
            if (!str_contains($path, self::VITE_ASSET_TOKEN)) {
                return;
            }

            $matches = [];
            preg_match(sprintf('/%s(.+)$/', self::VITE_ASSET_TOKEN), $path, $matches);
            if (count($matches) < 2) {
                return;
            }

            try {
                $asset = Vite::instance()->resolveAsset($matches[1]);
            } catch (\Throwable $e) {
                // Unfotunately, October swallows all exceptions from this event handler, so we can only log the error.
                logger()->error("[OFFLINE.Vite] Failed to include asset '${matches[1]}': {$e->getMessage()}", ['exception' => $e]);
                return;
            }

            if (!$asset instanceof Asset) {
                return;
            }

            if ($asset->env === Asset::ENV_PROD) {
                $controller = Controller::getController() ?? new Controller();

                $path = $controller->themeUrl($asset->path);
            } else {
                $path = $asset->path;
            }

            foreach ($asset->attributes as $attribute => $value) {
                $attributes[$attribute] = $value;
            }
        });
    }
}
