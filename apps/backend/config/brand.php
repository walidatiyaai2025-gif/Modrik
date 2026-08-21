<?php

$repositoryTokensPath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'packages'.DIRECTORY_SEPARATOR.'design-tokens'.DIRECTORY_SEPARATOR.'tokens.json';
$packagedTokensPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'brand'.DIRECTORY_SEPARATOR.'tokens.json';

$tokensPath = is_file($repositoryTokensPath)
    ? $repositoryTokensPath
    : $packagedTokensPath;

$tokenContents = @file_get_contents($tokensPath);

if ($tokenContents === false) {
    throw new RuntimeException(
        "Unable to read canonical design tokens. Checked {$repositoryTokensPath} and {$packagedTokensPath}.",
    );
}

$tokens = json_decode($tokenContents, true, flags: JSON_THROW_ON_ERROR);

return [
    'name' => $tokens['meta']['brand'],
    'colors' => [
        'primary' => $tokens['color']['brand']['teal']['$value'],
        'navy' => $tokens['color']['brand']['navy']['$value'],
        'blue' => $tokens['color']['brand']['blue']['$value'],
    ],
];
