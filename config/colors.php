<?php

/**
 * Satu sumber kebenaran warna Zivana ERP.
 *
 * Jangan menulis nilai HEX baru langsung di view atau stylesheet. Tambahkan
 * token di sini, lalu gunakan colorToken() atau CSS variable hasil
 * colorCssVariables().
 */
return [
    'page-title' => '#171717',
    'modal-text' => '#111111',
    'report-table-heading' => '#4E4E4E',
    'report-template-text' => '#363636',
    // Neutral ramp
    'neutral-50' => '#FCFCFD',
    'neutral-75' => '#E4E3E5',
    'neutral-100' => '#CBCBCD',
    'neutral-150' => '#B4B3B6',
    'neutral-200' => '#9D9C9F',
    'neutral-300' => '#868689',
    'neutral-400' => '#717074',
    'neutral-500' => '#5C5B5F',
    'neutral-600' => '#47474B',
    'neutral-700' => '#343338',
    'neutral-800' => '#222126',
    'neutral-900' => '#111015',
    'neutral-black' => '#040404',
    'neutral-white' => '#FFFFFF',

    // Brand red ramp
    'red-50' => '#FFF2F0',
    'red-100' => '#FED2CD',
    'red-200' => '#F8B3AC',
    'red-300' => '#EE958D',
    'red-400' => '#E3766E',
    'red-500' => '#D7554F',
    'red-600' => '#C92C2F',
    'red-700' => '#910A16',
    'red-800' => '#570107',
    'red-900' => '#240000',

    // Status ramps
    'orange-50' => '#FFF3E6',
    'orange-100' => '#FFE6CA',
    'orange-200' => '#FFD9AE',
    'orange-300' => '#FECC91',
    'orange-400' => '#FDBF72',
    'orange-500' => '#FCB14E',
    'orange-600' => '#FAA30F',
    'orange-700' => '#A56B0A',
    'orange-800' => '#583703',
    'orange-900' => '#180900',
    'green-50' => '#ECF9F0',
    'green-100' => '#CEEAD6',
    'green-200' => '#B0DBBD',
    'green-300' => '#92CBA4',
    'green-400' => '#72BC8C',
    'green-500' => '#4FAC74',
    'green-600' => '#1F9D5C',
    'green-700' => '#006B3A',
    'green-800' => '#003C1E',
    'green-900' => '#001305',
    'blue-600' => '#4344E8',

    'sidebar-connector' => '#B4B4B4',

    // Button / filter colors. Other variants reuse the existing brand/neutral ramps.
    'button-outline-text' => '#272727',
    'button-tab-inactive-text' => '#8B8B8B',
    'button-tab-inactive-border' => '#DDDDDD',

    // Semantic tokens confirmed for the revised Login specification.
    'page-background' => '#FCFCFD',
    'login-heading' => '#47474B',
    'login-muted' => '#868689',
    'login-label' => '#656565',
    'login-input-text' => '#111015',
    'login-brand-surface' => '#C92C2F',
    'login-brand-text' => '#FCFCFD',
    'login-preview-text' => '#2D2D2D',
    'login-preview-row' => '#FBFBFB',
    'login-preview-divider' => '#F1F1F1',
    'login-preview-success' => '#006B3A',
    'login-callout-surface' => '#FFF2F0',
    'login-callout-border' => '#FED2CD',
    'shadow-black' => '#000000',
    // 4% alpha representation of shadow-black for the Login input inset.
    'login-input-shadow' => '#0000000A',
];
