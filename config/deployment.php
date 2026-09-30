<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Git remote & branch
    |--------------------------------------------------------------------------
    |
    | "Pull latest changes" fetches this remote and hard-resets the working
    | copy to it. Leave DEPLOY_GIT_BRANCH unset to auto-detect whatever branch
    | is currently checked out.
    |
    */

    'remote' => env('DEPLOY_GIT_REMOTE', 'origin'),

    'branch' => env('DEPLOY_GIT_BRANCH'),

    /*
    |--------------------------------------------------------------------------
    | Frontend build
    |--------------------------------------------------------------------------
    |
    | Whether a deploy runs `npm ci && npm run build`. Disable if this server
    | has no Node toolchain and assets are built elsewhere.
    |
    */

    'run_npm_build' => (bool) env('DEPLOY_RUN_NPM_BUILD', true),

    /*
    |--------------------------------------------------------------------------
    | Command timeout
    |--------------------------------------------------------------------------
    |
    | Seconds allowed for each individual step (git, composer, npm, artisan).
    |
    */

    'command_timeout' => (int) env('DEPLOY_COMMAND_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Post-deploy commands
    |--------------------------------------------------------------------------
    |
    | Semicolon-separated shell commands run, in order, after every successful
    | deploy or rollback — e.g. restarting Reverb or reloading PHP-FPM, which
    | this app has no fixed process-manager convention for. These are trusted
    | strings the server operator wrote into .env, not user input. Left empty,
    | this step is skipped entirely.
    |
    | Example: DEPLOY_POST_COMMANDS="sudo systemctl restart hrm-reverb;sudo systemctl reload php8.3-fpm"
    |
    */

    'post_deploy_commands' => array_values(array_filter(array_map(
        'trim',
        explode(';', (string) env('DEPLOY_POST_COMMANDS', ''))
    ))),

];
