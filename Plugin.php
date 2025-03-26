<?php

namespace OFFLINE\Vite;

use October\Rain\Support\Facades\Event;
use OFFLINE\Vite\Classes\Vite;
use System\Classes\PluginBase;
use Throwable;

/**
 * Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Use this string to include a vite asset
     * via the addJs/addCss methods.
     */
    public const VITE_ASSET_TOKEN = 'vite:';

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
                'vite' => [Vite::class, 'includeAssets', false],
            ],
        ];
    }

    public function register()
    {
        Event::listen('cms.page.start', function () {
            Vite::instance();
        });

        // Replace the `vite:` token when adding JS/CSS assets.
        Event::listen('cms.assets.render', function ($type, &$result) {
            $vite = Vite::instance();

            $lines = array_map('trim', array_filter(explode("\n", $result)));

            foreach ($lines as $number => $line) {
                if (!str_contains($line, self::VITE_ASSET_TOKEN)) {
                    continue;
                }

                $matches = [];
                preg_match(sprintf('/%s([^">]+)/', self::VITE_ASSET_TOKEN), $line, $matches);

                if (count($matches) < 2) {
                    continue;
                }

                try {
                    $asset = $vite->resolveAsset($matches[1]);
                } catch (Throwable $e) {
                    // Unfortunately, October swallows all exceptions from this event handler, so we can only log the error.
                    logger()->error("[OFFLINE.Vite] Failed to include asset '{$matches[1]}': {$e->getMessage()}", ['exception' => $e]);

                    return;
                }

                // Replace the original line with the included asset.
                $lines[$number] = $asset->render();
            }

            $result = implode("\n", $lines);
        });
    }
}
