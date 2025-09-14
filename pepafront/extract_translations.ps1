# Script de PowerShell para extraer cadenas de $translate.instant y generar un archivo JSON

# Configuración
$directoryPath = ".\src"  # Cambia esto a la ruta de tu proyecto (por ejemplo, carpeta src de Angular)
$fileExtensions = @("*.ts", "*.html")  # Extensiones de archivos a buscar
$outputFile = "extracted-translations.json"  # Archivo de salida para las traducciones
$localeItFile = ".\langs\locale-it.json"  # Ruta al archivo locale-it.json existente
$regex = '\$translate\.instant\(''([^'']*)''\)'  # Regex para capturar $translate.instant('texto')

# Diccionario para almacenar traducciones existentes (de locale-it.json)
$translationsDict = @{}
if (Test-Path $localeItFile) {
    $localeItContent = Get-Content $localeItFile -Raw | ConvertFrom-Json
    foreach ($property in $localeItContent.PSObject.Properties) {
        $translationsDict[$property.Name] = $property.Value
    }
}

# Conjunto para almacenar cadenas únicas extraídas
$translations = New-Object System.Collections.Generic.HashSet[string]

# Función para procesar un archivo
function Process-File {
    param (
        [string]$filePath
    )
    $content = Get-Content $filePath -Raw
    if ($content -match $regex) {
        $matches = [regex]::Matches($content, $regex)
        foreach ($match in $matches) {
            $translations.Add($match.Groups[1].Value) | Out-Null
        }
    }
}

# Buscar archivos recursivamente
foreach ($ext in $fileExtensions) {
    Get-ChildItem -Path $directoryPath -Recurse -Include $ext | ForEach-Object {
        Write-Host "Procesando archivo: $($_.FullName)"
        Process-File -filePath $_.FullName
    }
}

# Crear objeto JSON con las traducciones
$output = @{}
foreach ($str in $translations) {
    # Usa la traducción existente de locale-it.json o la cadena original como placeholder
    $output[$str] = if ($translationsDict.ContainsKey($str)) { $translationsDict[$str] } else { $str }
}

# Guardar el resultado en un archivo JSON
$output | ConvertTo-Json -Depth 10 | Out-File -FilePath $outputFile -Encoding UTF8

Write-Host "Extracción completada. Las traducciones se han guardado en $outputFile"
Write-Host "Se encontraron $($translations.Count) cadenas únicas."