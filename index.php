<?php

require_once './Varien/Object.php';
require_once './Discount.php';

define('SNO', 'osn');

//ТЕстовые данные АТОЛА. Украл в Атоловской утилите для винды
define('LOGIN', 'atolonlinetest2');
define('PASSWORD', 'LpywGxLi7');
define('GROUP', 'AtolOnline2-Test');
define('PAYMENT_ADDRESS', 'test2.atol.ru');
define('INN', '7717586110');

define('URL', 'https://online.atol.ru/possystem/v3/');
define('TOKEN_URL', URL . 'getToken');
define('SELL_URL', URL . GROUP . '/sell');
define('CHECK_URL', URL . GROUP . '/report');

$map = [];
$Ids = [
    "000000278",
    "000000270",
    "000000262",
    "000000260",
    "000000258",
    "000000256",
    "000000253",
    "000000251",
    "000000249",
    "000000228",
    "000000227",
    "000000217",
    "000000212",
    "000000206",
    "000000204",
    "000000201",
    "000000200",
    "000000187",
    "000000186",
    "000000184",
    "000000180",
    "000000182",
    "000000181",
    "000000176",
    "000000170",
    "000000161",
    "000000160",
    "000000159",
    "000000144",
    "000000133",
    "000000127",
    "000000124",
    "000000104",
    "000000102",
    "000000094",
    "000000089",
    "000000080",
    "000000078",
    "000000075",
    "000000072"
];

function otfiltroBATb() {
    global $Ids;
    $all = razobratFile();
    $roTOBbIE = [];
    foreach ($all as $value) {
        //Пропускаем невидимые Айтемы
        if ($value['parent_item_id']) {
            continue;
        }

        if (in_array($value["increment_id"], $Ids)) {
            $roTOBbIE[$value["increment_id"]][] = $value;
        }
    }

    return $roTOBbIE;
}

function razobratFile() {
    $row = 1;
    $header = [];
    $result = [];
    if (($handle = fopen("example.csv", "r")) !== FALSE) {
        $header = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== FALSE) {
            foreach ($data as $col => $colVal) {
                $newKey = $header[$col];
                unset($data[$col]);
                $data[$newKey] = $colVal;
            }
            $result[] = $data;
        }
        fclose($handle);
    }
    return $result;
}

function generateJsonPost($receipt, $order) {
    $discountHelper = new Discount();

    $shipping_tax = 'vat18';
    $tax_value = 'vat18';

    $order->setShippingDescription("Доставка товара");

    $recalculatedReceiptData = $discountHelper->getRecalculated($receipt, $tax_value, '', $shipping_tax);
    $recalculatedReceiptData['items'] = array_values($recalculatedReceiptData['items']);

    $post = [
        'external_id' => "order_{$order->getIncrementId()}_manu",
        'service' => [
            'payment_address' => PAYMENT_ADDRESS,
            'callback_url' => '',
            'inn' => INN
        ],
        'timestamp' => date('d-m-Y H:i:s', time()),
        'receipt' => [],
    ];

    $receiptTotal = round($receipt->getGrandTotal(), 2);

    $post['receipt'] = [
        'attributes' => [
            'sno' => SNO,
            'phone' => '',
            'email' => $order->getCustomerEmail(),
        ],
        'total' => $receiptTotal,
        'payments' => [],
        'items' => [],
    ];

    $post['receipt']['payments'][] = [
        'sum' => $receiptTotal,
        'type' => 1
    ];

//        $recalculatedReceiptData['items'] = array_map([$this, 'sanitizeItem'], $recalculatedReceiptData['items']);
    $post['receipt']['items'] = $recalculatedReceiptData['items'];

    return json_encode($post);
}

function getJeiCOHbI($toSend) {
    $jsons = [];

    foreach ($toSend as $incId => $orderAndItem) {
        //Fill Order
        $order = new Varien_Object();
        $order->addData($orderAndItem[0]);

        //Add Items
        foreach ($orderAndItem as $value) {
            $item = new Varien_Object();
            $item->setData('id', $value['item_id']);
            $item->setData('row_total_incl_tax', $value['row_total_incl_tax']);
            $item->setData('price_incl_tax', $value['price_incl_tax']);
            $item->setData('discount_amount', $value['discount_amount(2)']);
            $item->setData('qty', $value['qty_ordered']);
            $item->setData('name', mb_substr($value['name'], 0, 64));

            $items = (array) $order->getData('all_items');
            $items[] = $item;
            $order->setData('all_items', $items);
        }

        //Генерим ЖСОН
        $jsons[] = generateJsonPost($order, $order);
    }

    return $jsons;
}

function addLog($message) {
    $message .= "\n";
    file_put_contents(dirname(__FILE__) . '/LOG.log', $message, FILE_APPEND);
}

function requestApiPost($url, $arpost) {
    // @codingStandardsIgnoreStart
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($arpost) ? http_build_query($arpost) : $arpost);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if ($result === false) {
        addLog('Curl error: ' . curl_error($ch));
        return false;
    }
    curl_close($ch);
    // @codingStandardsIgnoreEnd
    addLog('RESPONSE FROM ATOL: ' . $result);
    return $result;
}

function requestApiGet($url) {
    // @codingStandardsIgnoreStart
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if ($result === false) {
        addLog('Curl error: ' . curl_error($ch));
        return false;
    }
    curl_close($ch);
    // @codingStandardsIgnoreEnd
    addLog("Response From Atol: \t" . $result);
    return $result;
}

function getToken() {
    static $token;

    if ($token) {
        return $token;
    }
    $data = [
        'login' => LOGIN,
        'pass' => PASSWORD
    ];

    $getRequest = requestApiPost(TOKEN_URL, json_encode($data));

    if (!$getRequest) {
        throw new Exception(('There is no response from Atol.'));
    }

    $decodedResult = json_decode($getRequest);

    if (!$decodedResult->token || $decodedResult->token == '') {
        throw new Exception(('Response from Atol does not contain valid token value. Response: ') . strval($getRequest));
    }

    $token = $decodedResult->token;

    return $token;
}

function sendToAtol($json) {
    addLog('Request to ATOL json: ' . $json);

    $token = getToken();
    $url = SELL_URL . '?tokenid=' . $token;

    return requestApiPost($url, $json);
}

function obrabotkaPEKBECTA($PEKBECT) {
    $request = json_decode($PEKBECT);
    $uuid = isset($request->uuid) ? $request->uuid : null;

    if (!$uuid) {
        addLog('ERROR: ', $PEKBECT);
    }

    file_put_contents(dirname(__FILE__) . '/uuid.log', $uuid . "\n", FILE_APPEND);

    return $uuid;
}

function saveMap($extId, $uuid) {
    $extId = str_replace('order_', '', $extId);
    $extId = str_replace('_manual', '', $extId);

    $m = $extId . "\t\t\t" . $uuid . "\n";
    file_put_contents(dirname(__FILE__) . '/map.log', $m, FILE_APPEND);
}

function getExtId($json) {
    $pbj = json_decode($json);

    if (isset($pbj->external_id) && $pbj->external_id) {
        return $pbj->external_id;
    }

    return false;
}

function checkStatus($uuid) {
    global $map;

    $token = getToken();

    $url = CHECK_URL . '/' . $uuid . '?tokenid=' . $token;

    $getRequest = requestApiGet($url);

    $message = $map[$uuid] . "\t\t" . $uuid . "\t\t" . $getRequest . "\n";
    file_put_contents(dirname(__FILE__) . '/result.log', $message, FILE_APPEND);
}

// ====================================== START HERE ====================
$toSend = otfiltroBATb();

$jsons = getJeiCOHbI($toSend);

$uuids = [];
foreach ($jsons as $key => $json) {
    $req = sendToAtol($json);
    $uuid = obrabotkaPEKBECTA($req);

    $map[$uuid] = getExtId($json);
    $uuids[] = $uuid;

    saveMap(getExtId($json), $uuid);
    sleep(1);
}

foreach ($uuids as $key => $uuid) {
    checkStatus($uuid);
    sleep(1);
}