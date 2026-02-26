<?php
// agendar/apply_agendar_schema.php

require_once __DIR__ . '/../db.php';

echo "<pre>\n";
echo "=== Aplicando Schema do Módulo Agendar ===\n\n";

$sqlFile = __DIR__ . '/agendar_schema.sql';

if (!file_exists($sqlFile)) {
    die("❌ Arquivo SQL não encontrado: {$sqlFile}\n");
}

$sql = file_get_contents($sqlFile);

try {
    $pdo->exec($sql);
    echo "✅ Schema aplicado com sucesso!\n";
}
catch (PDOException $e) {
    echo "❌ Erro ao aplicar schema: " . $e->getMessage() . "\n";
}

echo "</pre>\n";