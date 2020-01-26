<?php
if (!isset($_POST['blob'])) {
    echo "blob key missing in POST data\n";
    exit(1);
}
$innerJson = base64_decode($_POST['blob']);

$inner = json_decode($innerJson);
if ($inner === null) {
    echo "Error decoding inner JSON data\n";
    exit(1);
}
$buyRequest = json_decode(base64_decode($inner->blob));
if ($buyRequest === null) {
    echo "Error decoding encrypted inner JSON data\n";
    exit(1);
}
if (!isset($buyRequest->uuid)) {
    echo "uuid key missing in JSON data\n";
    exit(1);
}
if (!isset($buyRequest->identifier)) {
    echo "identifier key missing in JSON data\n";
    exit(1);
}

ini_set('html_errors', false);

$productFiles = glob(
    __DIR__ . '/../developers/*/products/'
    . $buyRequest->identifier . '.json'
);
if (!count($productFiles)) {
    echo "Cannot find product file for product identifier\n";
    exit(1);
}
$product = json_decode(file_get_contents($productFiles[0]))->products[0];
if ($product === null) {
    echo "could not find product in purchases file\n";
    exit(1);
}

$payload = $product;
$payload->uuid = $buyRequest->uuid;

//"god of blades" and "pinball arcade" want double-encrypted responses
// muffin knights works with single encryption
$enc1 = [
    'key'  => base64_encode('0123456789abcdef'),
    'iv'   => 't3jir1LHpICunvhlM76edQ==',//random bytes
    'blob' => base64_encode(
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ),
];

$enc2 = [
    'key'  => base64_encode('0123456789abcdef'),
    'iv'   => 't3jir1LHpICunvhlM76edQ==',//random bytes
    'blob' => base64_encode(
        json_encode($enc1, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ),
];
echo json_encode($enc2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
?>
