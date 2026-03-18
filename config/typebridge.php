<?php

declare(strict_types=1);

return [
    // Default output directory used when --output-path is not provided.
    // The CLI --output-path option always overrides this value.
    'output' => [
        'base_path' => resource_path('typescript'),
    ],

    // Directories scanned recursively for PHP classes that use #[TypeScriptResource].
    'sources' => [
        app_path('Http/Resources'),
    ],

    // Generation-level options.
    'generation' => [
        // Add semicolons at line endings in generated TypeScript.
        'use_semicolons' => false,

        // Generate index.ts barrel file exporting all generated resources.
        'generate_index' => true,

        // Shared file name (without extension) used for shared_append content.
        // Example: "_api" => "_api.ts" when files.extension is "ts".
        'shared_file' => '_api',

        // Lines written once in the shared file.
        'shared_append' => [],

        // Per-resource template lines (masks).
        // Each template supports:
        // - lines: array of lines (required)
        // - name_equals: string|string[] (optional)
        // - name_starts_with: string (optional)
        // - name_ends_with: string (optional)
        // - name_matches: regex (optional)
        // - strip_suffix: string (optional, used for {base})
        // Placeholders in lines:
        // - {name} {base} {basePlural} {pascal} {camel} {snake} {kebab}
        'append_templates' => [],
    ],

    // File naming options.
    'files' => [
        // Output file extension.
        'extension' => 'ts',

        // Naming pattern placeholders:
        // - {name}: original attribute name (ex: UserProfile)
        // - {pascal}: PascalCase (ex: UserProfile)
        // - {camel}: camelCase (ex: userProfile)
        // - {snake}: snake_case (ex: user_profile)
        // - {kebab}: kebab-case (ex: user-profile)
        // Example: "{kebab}.types" => "user-profile.types.ts"
        // fileName on the attribute overrides this pattern for that resource.
        'naming_pattern' => '{name}',
    ],
];
