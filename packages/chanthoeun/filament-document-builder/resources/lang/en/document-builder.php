<?php

return [
    'labels' => [
        'template_details' => 'Template Details',
        'template_name' => 'Template Name',
        'template_type' => 'Document Type',
        'type_placeholder' => 'e.g. invoice, certificate, receipt',
        'database_model' => 'Database Model',
        'model_placeholder' => 'Select a model to use dynamic variables',
        'page_settings' => 'Page Settings',
        'setting' => 'Setting',
        'value' => 'Value',
        'additional_data_sources' => 'Additional Data Sources',
        'variable_name' => 'Variable Name',
        'variable_placeholder' => 'e.g. school',
        'retrieval_method' => 'Retrieval Method',
        'first_record' => 'First Record',
        'latest_record' => 'Latest Record',
        'document_designer' => 'Document Designer',
        'preview_pdf' => 'Preview PDF',
        'no_model_selected_title' => 'No Database Model Selected',
        'no_model_selected_body' => 'You must select a Database Model in the Template Details and click Save before you can preview the PDF.',
        'no_records_found_title' => 'No Records Found',
        'no_records_found_body' => 'There are no records in the :model table to preview with.',
        'created_on' => 'Created On',
        'last_updated' => 'Last Updated',
        'no_model_linked' => 'No model linked',
        'model_prefix' => 'Model: ',
    ],
    'validation' => [
        'invalid_model' => 'The selected value is not a valid Eloquent model class.',
    ],
];
