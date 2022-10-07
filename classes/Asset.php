<?php

namespace OFFLINE\Vite\Classes;

use Cms\Classes\Controller;
use October\Rain\Support\Arr;
use October\Rain\Support\Facades\Html;

class Asset
{
    public const ENV_PROD = 'PROD';
    public const ENV_DEV = 'DEV';
    public string $type = '';

    public const INTERNAL_ATTRIBUTES = ['path', 'render'];

    public function __construct(
        public string $path,
        public array $attributes = [],
        public array $relatedCss = [],
        public string $env = 'dev',
    ) {
        $this->attributes = Arr::except($attributes, self::INTERNAL_ATTRIBUTES);
    }

    public static function make(string $path, array $attributes = [], array $relatedCss = [])
    {
        return new self($path, $attributes, $relatedCss);
    }

    /**
     * Include an asset via the specified controller.
     * @param Controller $controller
     * @return void
     */
    public function include(Controller $controller)
    {
        if ($this->type === 'css') {
            $controller->addCss($this->path, $this->attributes);
            return;
        }

        $controller->addJs($this->path, $this->attributes);
        foreach ($this->relatedCss as $css) {
            $controller->addCss($css);
        }
    }


    public function viaDev()
    {
        $this->env = self::ENV_DEV;

        return $this;
    }

    public function viaProd()
    {
        $this->env = self::ENV_PROD;

        return $this;
    }

    public function asCss()
    {
        $this->type = 'css';

        return $this;
    }

    public function asJs()
    {
        $this->type = 'js';

        return $this;
    }

    public function render()
    {
        if ($this->type === 'css') {
            $attributes = Html::attributes([
                'rel' => 'stylesheet',
                'href' => $this->path,
                ...$this->attributes
            ]);

            return "<link $attributes>";
        }

        $attributes = Html::attributes(['src' => $this->path, ...$this->attributes]);

        $script = "<script $attributes></script>";
        foreach ($this->relatedCss as $css) {
            $attributes = Html::attributes(['rel' => 'stylesheet', 'href' => $css]);
            $script .= "<link $attributes>";
        }

        return $script;
    }
}
