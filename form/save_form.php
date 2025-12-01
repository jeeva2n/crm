<?php
header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);

$pdo = new PDO("mysql:host=localhost;dbname=alpha", "root", "");

// Prepare insert
$stmt = $pdo->prepare("
    INSERT INTO alpha_form_submissions 
    (form_type, order_number, vendor, approved_by, approval_date, purchased_from, purchased_by, purchase_date, items_json)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $input['form_type'],
    $input['order_number'],
    $input['vendor'],
    $input['approved_by'],
    $input['approval_date'],
    $input['purchased_from'],
    $input['purchased_by'],
    $input['purchase_date'],
    json_encode($input['items'])
]);

echo json_encode(["status" => "success"]);
?>
