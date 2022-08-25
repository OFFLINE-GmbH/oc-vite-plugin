<?php namespace OFFLINE\Vite;

use Backend;
use OFFLINE\Vite\Classes\Vite;
use System\Classes\PluginBase;

/**
 * Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Vite',
            'description' => 'Integrate Vite in October CMS',
            'author'      => 'OFFLINE',
            'icon'        => 'icon-bolt'
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'vite' => [Vite::class, 'init']
            ],
        ];
    }

}
