<?

/**
 * Подписываемся на событие нового заказа
 */
AddEventHandler('sale', 'OnOrderAdd', 'processOrder');

/**
 * Подписываемся на событие отправки формы
 */
AddEventHandler('form', 'onBeforeResultAdd', 'processForm');

/**
 * Хэш хука для заказов
 */
const ORDERS_HOOK = 'xxxxxxxxxx';

/**
 * Хэш хука для форм
 * (на случай, если он другой)
 */
const FORMS_HOOK = ORDERS_HOOK;

/**
 * Обработка новых заказов
 * @param $ID - ID заказа
 * @param $arFields - массив полей заказа
 */
function processOrder($ID, $arFields)
{

    $data = [
        order_id => $ID,
        name => $arFields["ORDER_PROP"][1],
        email => $arFields["ORDER_PROP"][2],
        phone => $arFields["ORDER_PROP"][3],
        index => $arFields["ORDER_PROP"][4],
        city => $arFields["ORDER_PROP"][5],
        address => $arFields["ORDER_PROP"][7],
        comment => $arFields["USER_DESCRIPTION"],
        total => formatNumber($arFields["PRICE"]),
        currency => $arFields["CURRENCY"],
        basket => ""
    ];

    /**
     * Перебор всех позиций заказа
     */
    foreach ($arFields["BASKET_ITEMS"] as $index => $item)
    {

        $data["basket"] .= $item["NAME"] . "\n";
        $data["basket"] .= formatNumber($item["PRICE"]) . " * " . formatNumber($item["QUANTITY"], 0) . " = ";
        $data["basket"] .= formatNumber($item["PRICE"] * $item["QUANTITY"]) . " " . $item["CURRENCY"];

        /**
         * Делаем доп. переносы после позиции
         * если она не последняя
         */
        if ($index + 1 !== count($arFields["BASKET_ITEMS"])) {
            $data["basket"] .= "\n\n";
        }

    }

    /**
     * В raw будут все данные, что отдаёт Bitrix
     * при ненадобности это можно закомментировать
     */
    $data["raw"] = $arFields;

    /**
     * Отправка
     */
    send(ORDERS_HOOK, $data);

}

/**
 * Обработка отправки форм
 */
function processForm($WEB_FORM_ID, $arFields, $arrVALUES)
{
    /**
     * В raw будут все данные, что отдаёт Bitrix
     */
    $data = $arrVALUES;

    /**
     * Отправка
     */
    send(FORMS_HOOK, $data);

}

/**
 * Отправка даных на B2B (HTTP POST)
 * @param $hash - хэш хука
 * @param $data - данные для отправки
 */
function send($hash, $data)
{

    $url = 'https://b2b.rocketcrm.bz/api/channels/site/form?hash=' . $hash;
    $ch = curl_init($url);

    $encodedData = json_encode($data);
    
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    curl_exec($ch);
    
    curl_close($ch);

}

/**
 * Форматирование числа
 * (просто обёртка number_format с значениями по умолч.)
 */
function formatNumber($value, $decimals = 2, $dec_point = '.', $thousands_sep = ' ')
{
    return number_format($value, $decimals, $dec_point, $thousands_sep);
}

?>
