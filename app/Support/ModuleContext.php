<?php

namespace App\Support;

use App\Enums\ServiceModule;
use App\Models\User;
use Illuminate\Http\Request;

class ModuleContext
{
    public static function fromRequest(Request $request, ?User $user = null): ServiceModule
    {
        $user ??= $request->user();
        $requested = $request->query('module', $request->input('module'));
        $module = $requested ? ServiceModule::tryFrom((string) $requested) : null;

        if ($module && $user && $user->canAccessModule($module)) {
            return $module;
        }

        return $user?->defaultModule() ?? ServiceModule::Gares;
    }

    public static function financialScope(ServiceModule $module): string
    {
        return $module === ServiceModule::Courrier ? 'courrier' : 'gares';
    }

    public static function financialTitle(ServiceModule $module, string $resource): string
    {
        return match ([$module, $resource]) {
            [ServiceModule::Courrier, 'recettes'] => 'Recettes courrier',
            [ServiceModule::Courrier, 'depenses'] => 'Dépenses courrier',
            [ServiceModule::Courrier, 'versements'] => 'Versements courrier',
            default => ucfirst($resource),
        };
    }
}
