# WAVE PLUS Updated Seeders

This package contains synchronized seeders based on the migration set supplied with the project.

## Protected authentication data

`RolesAndPermissionsSeeder` is kept unchanged and is responsible for users, roles, and permissions.
Other seeders do not truncate or recreate authentication data.

## Main changes

- Catalog seed data no longer writes removed compatibility fields.
- Catalog service seed data no longer writes `applicable_equipment_classes` / `applicable_agent_types`.
- Equipment Blueprints no longer write obsolete category/product/template fields.
- Blueprint option/value seed data now includes stable `code`, `data_type`, `numeric_value`, and `unit` where applicable.
- Blueprint servicing/consumable seed data now supports structured applicability conditions and option-based quantity calculation.
- Every catalog item is guaranteed to have at least one variant; missing items receive a `Default` variant.
- `DocumentSequenceSeeder` is repeatable without resetting authentication data.
- Duplicate legacy `BusinessPartnerSeeder.php` was removed; `CrmSeeder.php` is the active BP seed source.
- `CrmSeeder.php` resolves user references by email instead of assuming user IDs 1/2.
- `ClientAssetsSeeder.php` no longer owns document sequences; `DocumentSequenceSeeder.php` is the single source.

## Important

The uploaded archive did not contain the Laravel application/vendor runtime, so these seeders were syntax-checked with `php -l` but were not executed through `php artisan migrate:fresh --seed` in this environment.

Run in the full WAVE PLUS project:

```bash
php artisan migrate:fresh --seed
php artisan db:seed
php artisan db:seed --class=CatalogSeeder
```

Before running against any non-disposable database, take a backup and review reset/truncate behavior of the development seeders.
