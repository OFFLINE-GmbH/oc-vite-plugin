## Vite ⚡integration for October CMS

This plugin provides simple integration of [Vite](https://vitejs.dev/) for [October CMS](https://octobercms.com/) (Versions 3+).

## Setup

Install the plugin using composer

```bash
composer require offline/oc-vite-plugin
```

For the Vite integration to work, you first need to set the `VITE_MANIFEST`
env variable to the path of the manifest file generated by Vite.

```env
# /themes/your-theme/assets/build/manifest.json
VITE_MANIFEST=assets/build/manifest.json
```

Then make sure to place the `{% styles %}` and `{% scripts %}` tags in your layout,
so assets are included correctly.

## Configuring Vite

Install Vite in your theme [according to the official docs](https://vitejs.dev/guide/).

```bash
npm install --dev vite@latest
# or
yarn add -D vite@latest
```

You can adapt the following Vite configuration to bundle your theme assets:

```ts
// themes/your-theme/vite.config.ts
import { defineConfig } from 'vite'
import { resolve, basename } from 'path'

// Your JS/TS/CSS entrypoints.
const input = {
    main: resolve(__dirname, 'resources/ts/main.ts'),
    css: resolve(__dirname, 'resources/scss/main.scss'),
}

const themeName = __dirname.match(/themes\/([^\/]+)/)[1];

export default defineConfig({
    // Included assets will use this path as the base URL.
    base: `/themes/${themeName}/assets/build`,
    build: {
        rollupOptions: { input },
        manifest: true,
        emptyOutDir: true,
        // Output assets to /themes/your-theme/assets/build
        outDir: resolve(__dirname, 'assets/build'),
    },
    server: {
        hmr: {
            // Do not use encrypted connections for the HMR websocket.
            protocol: 'ws',
        },
    }
})

```

## Workflow

* To use Vite in development, start the Vite server using the `vite` command
* To build assets for production, use the `vite build` command

## Including Vite

Use the `vite()` function anywhere in Twig to include assets as well as the Vite Dev Server (depending on the environment).

You must provide an array of files to include as the first argument.
All paths are relative to the theme directory.

### Including assets

```twig
{# /themes/your-theme/resources/ts/main.ts #}
{{ vite([ 'resources/ts/main.ts' ]) }}
```

### Dev Server

By default, `local` is regarded as the dev environment. If your app environment is a dev environment,
the first call to the `{{ vite() }}` function will include the Vite Dev Server.

You can pass in different environments with the `devEnvs` parameter.

```twig
{# Regard "testing" as a dev environment #}
{{ vite([ ... ], { devEnvs: 'testing' }) }}

{# Regard "testing" or "local" as a dev environment #}
{{ vite([ ... ], { devEnvs: ['testing', 'local'] }) }}
```

### Host

By default, assets are loaded from `http://localhost:5173`. You can use the `host` parameter
to adjust this value.

```twig
{{ vite([ ... ], { host: 'http://localhost:8000' }) }}
```

