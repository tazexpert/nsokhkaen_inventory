<?php
/**
 * One-off backfill: re-assigns category_id for every EXISTING material to
 * the auto-derived single-Thai-letter category (see
 * includes/functions.php:resolveMaterialCategoryId()), for databases that had
 * materials before migration 004 introduced this behavior.
 *
 * Run from the project root: php database/migrations/004_backfill_material_categories.php
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDbConnection();

$materials = $pdo->query('SELECT id, name FROM materials')->fetchAll();
$updated = 0;
$skipped = 0;

foreach ($materials as $material) {
    $categoryId = resolveMaterialCategoryId($pdo, $material['name'], '');
    if ($categoryId === null) {
        $skipped++;
        echo "skip (no Thai letter): #{$material['id']} {$material['name']}" . PHP_EOL;
        continue;
    }

    $pdo->prepare('UPDATE materials SET category_id = :category_id WHERE id = :id')
        ->execute(['category_id' => $categoryId, 'id' => $material['id']]);
    $updated++;
}

echo "Done. Updated {$updated} material(s), skipped {$skipped} (no Thai letter in name)." . PHP_EOL;
