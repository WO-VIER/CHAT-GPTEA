namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\File;
use Barryvdh\Debugbar\Facade as Debugbar;

class LogToDebugbar
{
    public function handle($request, Closure $next)
    {
        // Lire le contenu du fichier laravel.log
        $logFile = storage_path('logs/laravel.log');
        if (File::exists($logFile)) {
            $logContent = File::get($logFile);

            // Ajouter le contenu des logs à Debugbar
            Debugbar::addMessage('Contenu de laravel.log', 'log');
            Debugbar::addMessage($logContent, 'log');  // Affiche tout le contenu de laravel.log
        }

        return $next($request);
    }
}
