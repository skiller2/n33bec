# Script de PowerShell para extraer cadenas de traducción usando múltiples regex

# Configuración
$directoryPath = ".\"  # Ruta del proyecto (ajusta según tu estructura)
$fileExtensions = @("*.ts", "*.html")  # Extensiones de archivos a buscar
$outputFile = "extracted-translations.json"  # Archivo de salida para todas las traducciones
$newTranslationsFile = "new-translations.json"  # Archivo para cadenas nuevas
$localeItFile = ".\langs\locale-it.json"  # Ruta al archivo locale-it.json existente

$startDelimiter = '{{'  # Delimitador de apertura HTML (ajusta si usas '<%')
$endDelimiter = '}}'    # Delimitador de cierre HTML
# Escapar delimitadores para regex
$escapedStart = [regex]::Escape($startDelimiter)
$escapedEnd = [regex]::Escape($endDelimiter)

# Lista de expresiones regulares basadas en el objeto regexs proporcionado
$regexPatterns = @(
    '\\/\\*\\s*i18nextract\\s*\\*\\/''((?:\\.|[^''\\])*)''',  # commentSimpleQuote
    '\\/\\*\\s*i18nextract\\s*\\*\\/"((?:\\.|[^"\\])*)"',      # commentDoubleQuote
    '\{\{.*?\s*(?:::)?''((?:\\.|[^''\\])*)''\s*\|\s*translate(?::.*?)?\s*\}\}',  # HtmlFilterSimpleQuote (simplificado, asume delimitadores {{}})
    '\{\{.*?\s*(?:::)?"((?:\\.|[^"\\])*)"\s*\|\s*translate(?::.*?)?\s*\}\}',    # HtmlFilterDoubleQuote
    '\{\{.*?\s*(?:::)?([^?]*\?[^:]*:[^|}]*)(\s*\|\s*translate(?::.*?)?)?\s*\}\}',  # HtmlFilterTernary
    '<(?:[^>"]|"(?:[^"]|\\/")*")*\stranslate(?:>|\s[^>]*>)([^<]*)',             # HtmlDirective
#    '<(?:[^>"]|"(?:[^"]|\\/")*")*\stranslate=''([^\']*)''[^>]*>([^<]*)',        # HtmlDirectiveSimpleQuote
    "$escapedStart\\s*(?:::)?'((?:\\\\.|[^'\\\\])*?)'\\s*\\|\\s*translate(?:\\:.*?)?\\s*$escapedEnd",  # HtmlFilterSimpleQuote (línea 19 corregida)
    '<(?:[^>"]|"(?:[^"]|\\/")*")*\stranslate="([^"]*)"[^>]*>([^<]*)',           # HtmlDirectiveDoubleQuote
    'translate="((?:\\.|[^"\\])*)".*angular-plural-extract="((?:\\.|[^"\\])*)"', # HtmlDirectivePluralLast
    'angular-plural-extract="((?:\\.|[^"\\])*)".*translate="((?:\\.|[^"\\])*)"', # HtmlDirectivePluralFirst
    'ng-bind-html="\s*''((?:\\.|[^''\\])*)''\s*\|\s*translate(?::.*?)?\s*"',     # HtmlNgBindHtml
    'ng-bind-html="\s*([^?]*?[^:]*:[^|}]*)(\s*\|\s*translate(?::.*?)?)?\s*"',    # HtmlNgBindHtmlTernary
    '\$translate\(\s*''((?:\\.|[^''\\])*)''[^)]*\)',                            # JavascriptServiceSimpleQuote
    '\$translate\(\s*"((?:\\.|[^"\\])*)"[^)]*\)',                               # JavascriptServiceDoubleQuote
    '\$translate\((?:\s*\[\s*(?:(?:''(?:(?:\\.|[^.*''\\])*)'')\s*,*\s*)+]\s*)\)',  # JavascriptServiceArraySimpleQuote
    '\$translate\((?:\s*\[\s*(?:(?:"(?:(?:\\.|[^.*"\\])*)")\s*,*\s*)+]\s*)\)',  # JavascriptServiceArrayDoubleQuote
    '\$translate\.instant\(\s*''((?:\\.|[^''\\])*)''[^)]*\)',                   # JavascriptServiceInstantSimpleQuote
    '\$translate\.instant\(\s*"((?:\\.|[^"\\])*)"[^)]*\)',                      # JavascriptServiceInstantDoubleQuote
    '\$filter\(\s*''translate''\s*\)\s*\(\s*''((?:\\.|[^''\\])*)''[^)]*\)',     # JavascriptFilterSimpleQuote
    '\$filter\(\s*"translate"\s*\)\s*\(\s*"((?:\\.|[^"\\])*)"[^)]*\)'           # JavascriptFilterDoubleQuote
)

# Cargar traducciones existentes de locale-it.json
$translationsDict = @{}
if (Test-Path $localeItFile) {
    try {
        $localeItContent = Get-Content $localeItFile -Raw -Encoding UTF8 | ConvertFrom-Json
        foreach ($property in $localeItContent.PSObject.Properties) {
            $translationsDict[$property.Name] = $property.Value
        }
    } catch {
        Write-Warning "Error al leer $localeItFile : $_"
    }
}

# Conjunto para almacenar cadenas únicas extraídas
$translations = New-Object System.Collections.Generic.HashSet[string]
$newTranslations = New-Object System.Collections.Generic.HashSet[string]

# Función para procesar un archivo con todas las regex
function Process-File {
    param (
        [string]$filePath
    )
    try {
        $content = Get-Content $filePath -Raw -Encoding UTF8 -ErrorAction SilentlyContinue
        foreach ($regex in $regexPatterns) {
            $matches = [regex]::Matches($content, $regex, 'IgnoreCase')
            foreach ($match in $matches) {
                # Procesar cada grupo de captura (puede haber múltiples grupos en algunas regex)
                for ($i = 1; $i -lt $match.Groups.Count; $i++) {
                    $key = $match.Groups[$i].Value
                    if ($key -and $key -notmatch '^\s*$') {  # Ignorar cadenas vacías
                        $translations.Add($key) | Out-Null
                        if (-not $translationsDict.ContainsKey($key)) {
                            $newTranslations.Add($key) | Out-Null
                        }
                    }
                }
            }
        }
    } catch {
        Write-Warning "Error al procesar $filePath : $_"
    }
}

# Buscar archivos recursivamente
foreach ($ext in $fileExtensions) {
    Get-ChildItem -Path $directoryPath -Recurse -Include $ext -ErrorAction SilentlyContinue | ForEach-Object {
        Write-Host "Procesando archivo: $($_.FullName)"
        Process-File -filePath $_.FullName
    }
}

# Crear objeto JSON con todas las traducciones
$output = @{}
foreach ($str in $translations) {
    $output[$str] = if ($translationsDict.ContainsKey($str)) { $translationsDict[$str] } else { $str }
}

# Crear objeto JSON con solo las cadenas nuevas
$newOutput = @{}
foreach ($str in $newTranslations) {
    $newOutput[$str] = $str
}

# Guardar resultados
try {
    $output | ConvertTo-Json -Depth 10 | Out-File -FilePath $outputFile -Encoding UTF8
    $newOutput | ConvertTo-Json -Depth 10 | Out-File -FilePath $newTranslationsFile -Encoding UTF8
    Write-Host "Extracción completada."
    Write-Host "Todas las traducciones guardadas en: $outputFile"
    Write-Host "Nuevas cadenas para traducir guardadas en: $newTranslationsFile"
    Write-Host "Se encontraron $($translations.Count) cadenas únicas, de las cuales $($newTranslations.Count) son nuevas."
} catch {
    Write-Error "Error al guardar los archivos JSON: $_"
}