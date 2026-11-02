import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    // Laravel's own @vite() tags always resolve the CSS/JS entry files
    // correctly at request time (using APP_URL), whichever URL the site is
    // served from - local XAMPP under the /School-Management-System/public
    // subfolder (see INSTALL.md step 7), or the production host at its
    // domain root. What this `base` setting controls is different: it's
    // the prefix Vite bakes directly into the *compiled* CSS/JS for their
    // own sub-resources, such as the bootstrap-icons @font-face src.
    //
    // A "./" (fully relative) base makes those sub-resource URLs resolve
    // relative to the CSS/JS file's own location rather than to the site's
    // domain root or any fixed subfolder. Per the CSS spec, url(...) inside
    // a stylesheet is always resolved relative to that stylesheet's own
    // URL - so as long as bootstrap-icons-*.woff2 sits next to app-*.css
    // (which it always does, both living in build/assets/), this one build
    // works correctly everywhere the project is deployed, with no
    // environment-specific ASSET_URL or rebuild step needed.
    base: './',
    plugins: [
        laravel({
            input: ['resources/css/app.scss', 'resources/css/app-rtl.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
