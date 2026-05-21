<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$outputDir = $root . '/docs/exports';
$functionalInput = $outputDir . '/Matriz_Pruebas_Funcionales.csv';
$nonFunctionalInput = $outputDir . '/Matriz_Pruebas_No_Funcionales.csv';

if (! is_file($functionalInput)) {
    fwrite(STDERR, "No se encontro la matriz funcional: {$functionalInput}\n");
    exit(1);
}

if (! is_file($nonFunctionalInput)) {
    fwrite(STDERR, "No se encontro la matriz no funcional: {$nonFunctionalInput}\n");
    exit(1);
}

/**
 * @return array<int, array<int, string>>
 */
function readDelimitedCsv(string $path, string $delimiter = "\t"): array
{
    $rows = [];
    $handle = fopen($path, 'rb');

    if ($handle === false) {
        throw new RuntimeException("No se pudo abrir el archivo: {$path}");
    }

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rows[] = array_map(
            static fn (?string $value): string => trim((string) $value),
            $row
        );
    }

    fclose($handle);

    return $rows;
}

/**
 * @param array<int, array<int, string>> $rows
 */
function writeCsv(string $path, array $rows): void
{
    $handle = fopen($path, 'wb');

    if ($handle === false) {
        throw new RuntimeException("No se pudo escribir el archivo CSV: {$path}");
    }

    fwrite($handle, "\xEF\xBB\xBF");

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);
}

/**
 * @param array<int, array<int, string>> $rows
 */
function writeSheet(Spreadsheet $spreadsheet, string $title, array $rows, int $sheetIndex): void
{
    $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet($sheetIndex);
    $sheet->setTitle($title);

    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 1;

        foreach ($row as $columnIndex => $value) {
            $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
            $sheet->setCellValueExplicit("{$column}{$excelRow}", $value, DataType::TYPE_STRING);
        }
    }

    if ($rows === []) {
        return;
    }

    $headerColumnCount = count($rows[0]);
    $lastColumn = Coordinate::stringFromColumnIndex($headerColumnCount);
    $headerRange = "A1:{$lastColumn}1";
    $dataRange = "A1:{$lastColumn}" . count($rows);

    $sheet->freezePane('A2');
    $sheet->setAutoFilter($headerRange);
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('D9EAF7');

    $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $sheet->getStyle($dataRange)->getAlignment()->setWrapText(true);

    foreach (range(1, $headerColumnCount) as $columnIndex) {
        $column = Coordinate::stringFromColumnIndex($columnIndex);
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
}

function cleanText(string $value): string
{
    $value = trim($value, " \t\n\r\0\x0B\"");
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return trim($value);
}

/**
 * @return array<int, string>
 */
function splitFunctionalScenarios(string $value): array
{
    $parts = preg_split('/\s*;\s*/', cleanText($value)) ?: [];
    $parts = array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));

    return $parts === [] ? ['Escenario pendiente de definir'] : $parts;
}

function titleCase(string $value): string
{
    $value = cleanText($value);
    if ($value === '') {
        return '';
    }

    return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
}

function endsWithPunctuation(string $value): bool
{
    return preg_match('/[.!?]$/u', $value) === 1;
}

function asSentence(string $value): string
{
    $value = titleCase($value);

    return endsWithPunctuation($value) ? $value : "{$value}.";
}

/**
 * @param array<int, string> $items
 */
function joinWithSemicolon(array $items): string
{
    $items = array_values(array_filter(array_map(
        static fn (string $item): string => cleanText($item),
        $items
    ), static fn (string $item): bool => $item !== ''));

    return implode('; ', $items);
}

function functionalPreconditions(string $module, string $requirement, string $level, string $scenario): string
{
    $items = [
        "Ambiente de QA disponible",
        "usuario con acceso al modulo {$module}",
        "flujo {$requirement} habilitado",
        "nivel de prueba {$level} disponible",
    ];

    $scenarioLower = mb_strtolower($scenario);

    if (str_contains($scenarioLower, 'correo') || str_contains($scenarioLower, 'email')) {
        $items[] = 'correo de prueba configurado';
    }

    if (str_contains($scenarioLower, 'token')) {
        $items[] = 'token de prueba generado';
    }

    if (
        str_contains($scenarioLower, 'archivo') ||
        str_contains($scenarioLower, 'layout') ||
        str_contains($scenarioLower, 'xml') ||
        str_contains($scenarioLower, 'pdf') ||
        str_contains($scenarioLower, 'imagen')
    ) {
        $items[] = 'archivo(s) de prueba disponible(s)';
    }

    if (
        str_contains($scenarioLower, 'datos') ||
        str_contains($scenarioLower, 'listado') ||
        str_contains($scenarioLower, 'datatable') ||
        str_contains($scenarioLower, 'widgets')
    ) {
        $items[] = 'datos semilla cargados';
    }

    if (
        str_contains($scenarioLower, 'password') ||
        str_contains($scenarioLower, 'sesion') ||
        str_contains($scenarioLower, 'credenciales') ||
        str_contains($scenarioLower, 'login')
    ) {
        $items[] = 'credenciales de prueba disponibles';
    }

    return asSentence(joinWithSemicolon($items));
}

function functionalSteps(string $module, string $requirement, string $scenario): string
{
    $items = [
        "Ingresar al modulo {$module}",
        "abrir el flujo {$requirement}",
        "ejecutar el escenario {$scenario}",
        "validar respuesta del sistema y evidencia generada",
    ];

    return implode(' -> ', array_map(static fn (string $item): string => titleCase($item), $items));
}

function functionalExpectedResult(string $scenario): string
{
    $scenarioLower = mb_strtolower($scenario);

    if (
        str_contains($scenarioLower, 'rechazo') ||
        str_contains($scenarioLower, 'invalido') ||
        str_contains($scenarioLower, 'incorrect') ||
        str_contains($scenarioLower, 'expirad') ||
        str_contains($scenarioLower, 'alterad') ||
        str_contains($scenarioLower, 'inactivo')
    ) {
        return 'El sistema rechaza la accion indicada, conserva la integridad del flujo y muestra el mensaje o validacion correspondiente.';
    }

    if (str_contains($scenarioLower, 'no acceso') || str_contains($scenarioLower, 'bloque')) {
        return 'El sistema impide el acceso o la accion no autorizada y mantiene protegido el recurso.';
    }

    if (str_contains($scenarioLower, 'throttle')) {
        return 'El sistema aplica el limite de intentos conforme a la politica configurada.';
    }

    if (str_contains($scenarioLower, 'redireccion')) {
        return 'El sistema redirige al usuario al destino correcto conforme a su perfil o estado.';
    }

    if (str_contains($scenarioLower, 'listado') || str_contains($scenarioLower, 'datatable')) {
        return 'La informacion se muestra correctamente en pantalla, sin errores y con datos consistentes.';
    }

    if (str_contains($scenarioLower, 'widgets')) {
        return 'Los widgets se muestran sin errores con la informacion disponible para el usuario.';
    }

    if (str_contains($scenarioLower, 'descarga')) {
        return 'La descarga se genera correctamente y el archivo resultante es utilizable.';
    }

    if (str_contains($scenarioLower, 'envio') || str_contains($scenarioLower, 'notificacion')) {
        return 'El sistema genera y entrega la notificacion esperada sin errores.';
    }

    if (
        str_contains($scenarioLower, 'create') ||
        str_contains($scenarioLower, 'alta') ||
        str_contains($scenarioLower, 'cread')
    ) {
        return 'El registro se crea correctamente, respetando validaciones y reglas de negocio.';
    }

    if (str_contains($scenarioLower, 'update') || str_contains($scenarioLower, 'edicion')) {
        return 'La actualizacion se aplica correctamente y los cambios quedan reflejados sin inconsistencias.';
    }

    if (str_contains($scenarioLower, 'delete') || str_contains($scenarioLower, 'borrado')) {
        return 'La eliminacion se ejecuta conforme a las restricciones del negocio y sin afectar informacion no relacionada.';
    }

    return "El sistema cumple correctamente con el escenario evaluado: {$scenario}.";
}

function nonFunctionalPreconditions(string $category, string $requirement, string $method): string
{
    $items = [
        'ambiente de QA disponible',
        "controles de {$category} habilitados",
        "alcance {$requirement} accesible",
        "metodo {$method} preparado para validacion",
        'bitacoras o monitoreo disponibles para evidencia',
    ];

    return asSentence(joinWithSemicolon($items));
}

function nonFunctionalSteps(string $category, string $requirement, string $test): string
{
    $items = [
        "Preparar el escenario de {$category}",
        "ubicar el alcance {$requirement}",
        "ejecutar la prueba {$test}",
        'validar respuesta, controles, logs y evidencia',
    ];

    return implode(' -> ', array_map(static fn (string $item): string => titleCase($item), $items));
}

function normalizeExpectedResult(string $value): string
{
    return asSentence($value);
}

/**
 * @param array<int, array<int, string>> $functionalRows
 * @return array<int, array<int, string>>
 */
function buildFunctionalExecutionRows(array $functionalRows): array
{
    $rows = [[
        'ID',
        'Modulo',
        'Requerimiento funcional',
        'Escenario de prueba',
        'Precondiciones',
        'Pasos ejecutados',
        'Resultado esperado',
        'Resultado obtenido',
        'Estatus',
    ]];

    foreach (array_slice($functionalRows, 1) as $row) {
        [$id, $module, $requirement, $level, $mandatoryCases] = array_pad($row, 5, '');

        foreach (splitFunctionalScenarios($mandatoryCases) as $scenario) {
            $rows[] = [
                cleanText($id),
                cleanText($module),
                titleCase($requirement),
                titleCase($scenario),
                functionalPreconditions($module, $requirement, $level, $scenario),
                functionalSteps($module, $requirement, $scenario),
                functionalExpectedResult($scenario),
                'Pendiente de ejecucion.',
                '',
            ];
        }
    }

    return $rows;
}

/**
 * @param array<int, array<int, string>> $nonFunctionalRows
 * @return array<int, array<int, string>>
 */
function buildNonFunctionalExecutionRows(array $nonFunctionalRows): array
{
    $rows = [[
        'ID',
        'Categoria',
        'Requerimiento no funcional',
        'Escenario de prueba',
        'Precondiciones',
        'Pasos ejecutados',
        'Resultado esperado',
        'Resultado obtenido',
        'Estatus',
    ]];

    foreach (array_slice($nonFunctionalRows, 1) as $row) {
        [$id, $category, $requirement, $test, $method, $acceptance] = array_pad($row, 6, '');

        $rows[] = [
            cleanText($id),
            cleanText($category),
            titleCase($requirement),
            titleCase($test),
            nonFunctionalPreconditions($category, $requirement, $method),
            nonFunctionalSteps($category, $requirement, $test),
            normalizeExpectedResult($acceptance),
            'Pendiente de ejecucion.',
            '',
        ];
    }

    return $rows;
}

/**
 * @param array<int, array<int, string>> $functionalRows
 * @param array<int, array<int, string>> $nonFunctionalRows
 * @return array<int, array<int, string>>
 */
function buildSummaryRows(array $functionalRows, array $nonFunctionalRows): array
{
    return [
        ['Seccion', 'Valor'],
        ['Sistema', 'Portal de Proveedores'],
        ['Fecha de generacion', date('Y-m-d H:i:s')],
        ['Filas funcionales para ejecucion', (string) max(count($functionalRows) - 1, 0)],
        ['Filas no funcionales para ejecucion', (string) max(count($nonFunctionalRows) - 1, 0)],
        ['Resultado obtenido por defecto', 'Pendiente de ejecucion'],
        ['Estatus', 'Se deja en blanco para captura manual de PASO / NO PASO'],
    ];
}

$functionalSourceRows = readDelimitedCsv($functionalInput);
$nonFunctionalSourceRows = readDelimitedCsv($nonFunctionalInput);

$functionalExecutionRows = buildFunctionalExecutionRows($functionalSourceRows);
$nonFunctionalExecutionRows = buildNonFunctionalExecutionRows($nonFunctionalSourceRows);
$summaryRows = buildSummaryRows($functionalExecutionRows, $nonFunctionalExecutionRows);

writeCsv($outputDir . '/Formato_Ejecucion_Pruebas_Funcionales.csv', $functionalExecutionRows);
writeCsv($outputDir . '/Formato_Ejecucion_Pruebas_No_Funcionales.csv', $nonFunctionalExecutionRows);

$spreadsheet = new Spreadsheet();
writeSheet($spreadsheet, 'Resumen', $summaryRows, 0);
writeSheet($spreadsheet, 'Ejecucion funcional', $functionalExecutionRows, 1);
writeSheet($spreadsheet, 'Ejecucion no funcional', $nonFunctionalExecutionRows, 2);

$writer = new Xlsx($spreadsheet);
$writer->save($outputDir . '/Formato_Ejecucion_Pruebas_Portal_Proveedores.xlsx');

echo "Formato de ejecucion generado en: {$outputDir}\n";
