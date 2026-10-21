<?php

/** Bundled static print fonts: no network, font installation, or writable vendor directory. */
final class EraporPrintFont
{
    public static function options(): array
    {
        return [
            'defaultFont' => 'Quicksand',
            'fontDir' => dirname(__DIR__, 2) . '/public/assets/fonts/quicksand',
            'fontCache' => sys_get_temp_dir(),
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ];
    }
}
