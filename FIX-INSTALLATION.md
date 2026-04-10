# Correctif installation

Le problème venait d'un mauvais échappement des namespaces dans `composer.json`.

Corrigé :
- `App\\` -> `App\`
- `Database\\Factories\\` -> `Database\Factories\`
- `Database\\Seeders\\` -> `Database\Seeders\`
- `Tests\\` -> `Tests\`
- `Illuminate\\Foundation\\ComposerScripts::postAutoloadDump` -> `Illuminate\Foundation\ComposerScripts::postAutoloadDump`

## Reprise rapide

```powershell
Remove-Item -Recurse -Force vendor, composer.lock -ErrorAction SilentlyContinue
composer install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
php artisan serve
```
