<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rating scale
    |--------------------------------------------------------------------------
    | The numeric scale every review rating and goal rating is scored on.
    | Tenants override the labels via settings.performance.rating_scale; the
    | bounds stay fixed so score aggregation is comparable across the org.
    */
    'min_rating' => 1,
    'max_rating' => 5,

    'rating_scale' => [
        1 => 'Unsatisfactory',
        2 => 'Needs improvement',
        3 => 'Meets expectations',
        4 => 'Exceeds expectations',
        5 => 'Outstanding',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default competency framework
    |--------------------------------------------------------------------------
    | Used to pre-fill a manager review's rating rows. Data, not code — a
    | tenant overrides the list via settings.performance.competencies
    | (array of {code, name}). Weights are optional and normalised at scoring
    | time; 0 means "equal weight".
    */
    'competencies' => [
        ['code' => 'job_knowledge', 'name' => 'Job knowledge & skills'],
        ['code' => 'quality_of_work', 'name' => 'Quality of work'],
        ['code' => 'productivity', 'name' => 'Productivity & timeliness'],
        ['code' => 'communication', 'name' => 'Communication'],
        ['code' => 'teamwork', 'name' => 'Teamwork & collaboration'],
        ['code' => 'initiative', 'name' => 'Initiative & ownership'],
        ['code' => 'reliability', 'name' => 'Reliability & attendance'],
        ['code' => 'leadership', 'name' => 'Leadership & mentoring'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default score weighting
    |--------------------------------------------------------------------------
    | How a cycle's overall employee score is split between goal attainment
    | and competency ratings. Each cycle stores its own copy (editable while
    | the cycle is a draft); these are the defaults when one is created.
    */
    'goal_weight' => 60,
    'competency_weight' => 40,

];
