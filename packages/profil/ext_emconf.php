<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'profil',
    'description' => 'Sitepackage für ein Portfolio-Profil',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'fluid_styled_content' => '13.4.0-13.4.99',
            'rte_ckeditor' => '13.4.0-13.4.99',
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Xmwhd\\Profil\\' => 'Classes',
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'Maik Demuth',
    'author_email' => 'connect@maidem.de',
    'author_company' => 'Xmwhd',
    'version' => '1.0.0',
];
