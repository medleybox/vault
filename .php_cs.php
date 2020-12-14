<?php

$finder = PhpCsFixer\Finder::create()
    ->in('src')
;

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'native_function_invocation' => [
            'include' => [NativeFunctionInvocationFixer::SET_COMPILER_OPTIMIZED],
            'scope' => 'namespaced',
            'strict' => false, // or remove this line, as false is default value
        ],
    ])
    ->setRiskyAllowed(false)
    ->setFinder($finder)
    ->setUsingCache(false)
;