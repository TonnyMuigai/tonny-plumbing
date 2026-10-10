<?php
declare(strict_types=1);

function branding_font_options(): array
{
    return [
        'system-ui'        => 'System (default, fastest)',
        'inherit'          => 'Inherit from body',
        'Inter'            => 'Inter (modern, clean)',
        'Poppins'          => 'Poppins (rounded, friendly)',
        'Roboto'           => 'Roboto (neutral)',
        'Open Sans'        => 'Open Sans (legible)',
        'Montserrat'       => 'Montserrat (bold display)',
        'Nunito'           => 'Nunito (soft rounded)',
        'Work Sans'        => 'Work Sans (versatile)',
        'DM Sans'          => 'DM Sans (contemporary)',
        'Georgia'          => 'Georgia (classic serif)',
        'Merriweather'     => 'Merriweather (elegant serif)',
        'Playfair Display' => 'Playfair Display (high-contrast serif)',
    ];
}

function branding_text_scale_options(): array
{
    return [
        '0.90' => 'Compact (90%)',
        '0.95' => 'Snug (95%)',
        '1.00' => 'Normal (100%)',
        '1.05' => 'Comfortable (105%)',
        '1.10' => 'Large (110%)',
        '1.15' => 'Larger (115%)',
        '1.25' => 'Extra large (125%)',
        '1.35' => 'Huge (135%)',
    ];
}

function branding_clean_hex(string $value, string $default = '#000000'): string
{
    $value = trim($value);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) return strtolower($value);
    if (preg_match('/^#[0-9a-fA-F]{3}$/', $value)) {
        return strtolower('#' . $value[1].$value[1] . $value[2].$value[2] . $value[3].$value[3]);
    }
    return $default;
}

function branding_lighten(string $hex, int $percent): string
{
    $hex = ltrim(branding_clean_hex($hex), '#');
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    $r = (int)round($r + (255 - $r) * $percent / 100);
    $g = (int)round($g + (255 - $g) * $percent / 100);
    $b = (int)round($b + (255 - $b) * $percent / 100);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function branding_darken(string $hex, int $percent): string
{
    $hex = ltrim(branding_clean_hex($hex), '#');
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    $r = (int)round($r * (100 - $percent) / 100);
    $g = (int)round($g * (100 - $percent) / 100);
    $b = (int)round($b * (100 - $percent) / 100);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function branding_contrast_text(string $hex): string
{
    $hex = ltrim(branding_clean_hex($hex), '#');
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    $luma = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $luma > 0.6 ? '#0f172a' : '#ffffff';
}

function render_brand_head(): string
{
    $primary = branding_clean_hex(setting('color_primary', '#102a43'));
    $accent  = branding_clean_hex(setting('color_accent',  '#0d9488'));
    $cta     = branding_clean_hex(setting('color_cta',     '#10b981'));

    $primaryHover = branding_lighten($primary, 10);
    $accentHover  = branding_darken($accent, 10);
    $ctaHover     = branding_darken($cta, 10);
    $onPrimary    = branding_contrast_text($primary);
    $onAccent     = branding_contrast_text($accent);
    $onCta        = branding_contrast_text($cta);

    $fontBody    = setting('font_body',    'system-ui');
    $fontHeading = setting('font_heading', 'inherit');

    $fontsToLoad = [];
    foreach ([$fontBody, $fontHeading] as $f) {
        if ($f !== '' && $f !== 'system-ui' && $f !== 'inherit' && $f !== 'Georgia') {
            $fontsToLoad[$f] = true;
        }
    }
    $fontsToLoad = array_keys($fontsToLoad);

    $favicon = setting('favicon_image_path', '');

    $bodyStack = ($fontBody === 'system-ui' || $fontBody === 'inherit' || $fontBody === '')
        ? 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'
        : '"' . $fontBody . '", ui-sans-serif, system-ui, sans-serif';

    $headingStack = ($fontHeading === 'inherit' || $fontHeading === '')
        ? $bodyStack
        : ($fontHeading === 'Georgia'
            ? 'Georgia, "Times New Roman", serif'
            : '"' . $fontHeading . '", ui-sans-serif, system-ui, sans-serif');

    $out  = '<script src="https://cdn.tailwindcss.com"></script>' . "\n";
    $out .= '<script>' . "\n";
    $out .= 'tailwind.config = {' . "\n";
    $out .= '  theme: {' . "\n";
    $out .= '    extend: {' . "\n";
    $out .= '      colors: {' . "\n";
    $out .= '        brand: {' . "\n";
    $out .= '          primary:"' . $primary . '",' . "\n";
    $out .= '          primaryHover:"' . $primaryHover . '",' . "\n";
    $out .= '          onPrimary:"' . $onPrimary . '",' . "\n";
    $out .= '          accent:"' . $accent . '",' . "\n";
    $out .= '          accentHover:"' . $accentHover . '",' . "\n";
    $out .= '          onAccent:"' . $onAccent . '",' . "\n";
    $out .= '          cta:"' . $cta . '",' . "\n";
    $out .= '          ctaHover:"' . $ctaHover . '",' . "\n";
    $out .= '          onCta:"' . $onCta . '"' . "\n";
    $out .= '        }' . "\n";
    $out .= '      },' . "\n";
    $out .= '      fontFamily: {' . "\n";
    $out .= '        sans: [' . json_encode($bodyStack) . '],' . "\n";
    $out .= '        heading: [' . json_encode($headingStack) . ']' . "\n";
    $out .= '      }' . "\n";
    $out .= '    }' . "\n";
    $out .= '  }' . "\n";
    $out .= '};' . "\n";
    $out .= '</script>' . "\n";

    if ($fontsToLoad) {
        $families = [];
        foreach ($fontsToLoad as $f) {
            $families[] = 'family=' . str_replace(' ', '+', $f) . ':wght@400;500;600;700;800;900';
        }
        $out .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $out .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        $out .= '<link href="https://fonts.googleapis.com/css2?' . implode('&', $families) . '&display=swap" rel="stylesheet">' . "\n";
    }

    // Global text scale
    $textScale = (float) setting('text_scale', '1.00');
    if ($textScale < 0.75) $textScale = 0.75;
    if ($textScale > 1.50) $textScale = 1.50;
    $textScalePercent = number_format($textScale * 100, 1, '.', '');

    $out .= '<style>';
    $out .= 'html{font-size:' . $textScalePercent . '%}';
    $out .= 'body{font-family:' . $bodyStack . '}';
    $out .= 'h1,h2,h3,h4,h5,h6{font-family:' . $headingStack . '}';
    $out .= '</style>' . "\n";

    if ($favicon !== '') {
        $out .= '<link rel="icon" href="' . e($favicon) . '">' . "\n";
    }

    return $out;
}