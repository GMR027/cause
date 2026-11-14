<?php

declare(strict_types=1);

return [
    'badge' => 'Tools',
    'title' => 'Statements',
    'subtitle' => 'Keep your PDF statements for whenever you need them. Only you can see them.',
    'notice' => [
        'title' => 'Before uploading files',
        'body' => 'Uploading statements is YOUR decision. You are not required to. The information is YOUR responsibility. The app only stores it for your consultation. Make sure the files do not contain information you do not want to store. You can delete any file whenever you want.',
        'version' => 'Notice version :version.',
    ],
    'consent' => [
        'title' => 'Consent to store files',
        'hint' => 'We will record the date, time and version of this notice. We do not analyze or extract information from your PDFs.',
        'checkbox' => 'I understand and accept the notice. I authorize the app to store these files for my consultation.',
        'submit' => 'Accept and enable',
    ],
    'auth' => [
        'title' => 'Confirm your password',
        'hint' => 'For security, type your password again to enter Statements.',
        'submit' => 'Enter',
    ],
    'upload' => [
        'title' => 'Upload a statement',
        'hint' => 'PDF only, maximum :max and :files files per account. No information is extracted from the file.',
        'file' => 'PDF file',
        'type' => 'Type',
        'submit' => 'Upload file',
    ],
    'usage' => [
        'title' => 'Stored files',
        'files' => 'of :max allowed',
    ],
    'list' => [
        'title' => 'Your statements',
        'empty' => 'You have not uploaded statements yet.',
        'name' => 'Name',
        'size' => 'Size',
        'date' => 'Date',
        'type' => 'Type',
        'confirm_delete' => 'Delete this file? This action cannot be undone.',
    ],
    'types' => [
        'banco' => 'Bank',
        'inversion' => 'Investing',
    ],
    'errors' => [
        'desktop_only' => 'Statements are only available on a desktop computer.',
        'consent_required' => 'You must accept the notice before uploading files.',
        'invalid_password' => 'The password is incorrect.',
        'reauth_required' => 'Confirm your password again to continue.',
        'upload_failed' => 'The file could not be uploaded: :message',
        'not_found' => 'The statement does not exist or does not belong to you.',
    ],
    'flash' => [
        'consent_saved' => 'Notice accepted. You can now upload files.',
        'uploaded' => 'Statement saved.',
        'deleted' => 'Statement deleted.',
    ],
];
