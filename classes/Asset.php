<?php

namespace OFFLINE\Vite\Classes;

class Asset
{
    public const ENV_PROD = 'PROD';
    public const ENV_DEV = 'DEV';

    public function __construct(
        public string $path,
        public array $attributes = [],
        public array $css = [],
        public array $js = [],
        public string $env = 'dev',
    ) {
    }

    public static function make(string $path, array $attributes = [], array $css = [], array $js = [])
    {
        return new self($path, $attributes, $css, $js);
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
}
