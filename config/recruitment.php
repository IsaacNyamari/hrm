<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default hiring pipeline
    |--------------------------------------------------------------------------
    | Ordered stage keys a new vacancy starts with. Each vacancy stores its own
    | copy (`vacancies.pipeline_stages`) so recruiters can tailor it. The last
    | stage is treated as the "hired" terminal stage.
    */
    'default_pipeline' => ['applied', 'screening', 'interview', 'offer', 'hired'],

    /*
    |--------------------------------------------------------------------------
    | Rejection reasons
    |--------------------------------------------------------------------------
    | Suggestions surfaced in the UI; free text is still allowed.
    */
    'rejection_reasons' => [
        'Skills mismatch',
        'Experience level',
        'Compensation expectations',
        'Failed assessment',
        'Withdrew / unresponsive',
        'Position filled',
        'Better suited candidate',
    ],

    /*
    |--------------------------------------------------------------------------
    | Applicant resume storage
    |--------------------------------------------------------------------------
    */
    'resume_disk' => 'local',
    'resume_max_kb' => 10240,

];
