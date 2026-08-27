<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Presentation\Support;

/**
 * Turns a raw (module, action) pair into a human-readable Spanish label
 * for the Role modal's permissions checklist — e.g. ('roles', 'edit')
 * -> "Editar roles". Pure presentation formatting, no business rule
 * lives here. Falls back to a readable default for any action/module
 * not in the map, so new modules/actions never render blank.
 */
final class PermissionLabelFormatter
{
    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'create' => 'Crear',
        'view' => 'Ver',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'search' => 'Buscar',
        'export_pdf' => 'Exportar PDF de',
        'export_excel' => 'Exportar Excel de',
        'create_technical_note' => 'Iniciar nota técnica de',
        'approve_manual' => 'Resolver aprobación manual de',
        'resolve_technical_note' => 'Ratificar o rechazar nota técnica de',
    ];

    /** @var array<string, string> */
    private const MODULE_LABELS = [
        'roles' => 'roles',
        'permissions' => 'permisos',
        'teachers' => 'docentes y atestados',
        'eligibility_catalogs' => 'catálogo de cursos',
        'eligibility_checks' => 'verificación de atinencia',
    ];

    public static function forHumans(string $module, string $action): string
    {
        $actionLabel = self::ACTION_LABELS[$action]
            ?? ucfirst(str_replace('_', ' ', $action));

        $moduleLabel = self::MODULE_LABELS[$module] ?? $module;

        return "{$actionLabel} {$moduleLabel}";
    }

    public static function module(string $module): string
    {
        return ucfirst(self::MODULE_LABELS[$module] ?? str_replace('_', ' ', $module));
    }

    public static function action(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    /** @return array<string, string> */
    public static function modules(): array
    {
        return array_map('ucfirst', self::MODULE_LABELS);
    }

    /** @return array<string, string> */
    public static function actions(): array
    {
        return self::ACTION_LABELS;
    }
}
