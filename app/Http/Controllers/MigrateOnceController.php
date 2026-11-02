<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * TEMPORARY - one-time migration runner for hosted servers without SSH
 * (InfinityFree has none). Visit this URL ONCE after uploading, then
 * delete the route from routes/web.php and re-upload. See DEPLOY.md
 * section 8.
 *
 * A controller (not a route closure) so `php artisan route:cache` works -
 * closures cannot be serialized into the cached routes file.
 */
class MigrateOnceController extends Controller
{
    public function __invoke(): Response
    {
        Artisan::call('migrate', ['--force' => true]);

        return response('<pre>'.Artisan::output().'</pre>');
    }
}
