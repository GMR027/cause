<?php
declare(strict_types=1);
return [
    'title' => 'Subcategories',
    'subtitle' => 'Organize the detail of your expenses inside each category.',
    'badge' => 'Tools',
    'new_title' => 'New subcategory',
    'new_hint' => 'It will be used in expenses, templates and recurring expenses.',
    'name' => 'Name',
    'name_placeholder' => 'E.g. Groceries',
    'part' => 'Category',
    'create' => 'Create subcategory',
    'save' => 'Save',
    'delete' => 'Delete',
    'confirm_delete' => 'Delete this subcategory? Historical expenses keep their name.',
    'empty' => 'There are no subcategories in this category yet.',
    'uses' => ':count use(s)',
    'back' => 'Back to settings',
    'flash' => [
        'created' => 'Subcategory created.',
        'updated' => 'Subcategory updated.',
        'deleted' => 'Subcategory deleted.',
    ],
    'errors' => [
        'generic' => 'The subcategory could not be saved. Please try again.',
        'name' => 'The name must be between 2 and 80 characters.',
        'part' => 'Choose a valid category.',
        'duplicate' => 'A subcategory with that name already exists.',
        'not_found' => 'The subcategory does not exist.',
    ],
];
