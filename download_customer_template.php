<?php
session_start();
require_once 'functions.php';
// requireAuth();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="customer_import_template.csv"');

$output = fopen('php://output', 'w');

// Headers matching your import logic
$headers = [
    'name', 
    'email', 
    'phone', 
    'company', 
    'address', 
    'city', 
    'state', 
    'zip', 
    'country', 
    'tax_id', 
    'credit_limit', 
    'notes'
];

fputcsv($output, $headers);

// Sample row
$sample = [
    'John Doe', 
    'john@example.com', 
    '123-456-7890', 
    'Example Corp', 
    '123 Main St', 
    'New York', 
    'NY', 
    '10001', 
    'USA', 
    'TAX-123', 
    '5000', 
    'Sample note'
];

fputcsv($output, $sample);
fclose($output);
exit;