<?php
declare(strict_types=1);

return [
    'requests' => [
        'requests_company_contact' => "client_kind <> 'company' OR NULLIF(TRIM(contact_person), '') IS NOT NULL",
        'requests_preferred_email' => "contact_method <> 'Email' OR NULLIF(TRIM(email), '') IS NOT NULL",
    ],
    'pool_details' => [
        'pool_details_positive_dimensions' => '(length_m IS NULL OR length_m > 0) AND
            (width_m IS NULL OR width_m > 0) AND (depth_m IS NULL OR depth_m > 0)',
    ],
    'projects' => [
        'projects_completion_date' => "(status = 'ongoing' AND completed_on IS NULL) OR
            (status = 'completed' AND completed_on IS NOT NULL AND completed_on >= started_on)",
    ],
];
