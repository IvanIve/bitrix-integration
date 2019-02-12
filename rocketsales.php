<?php

/**
 * Подписываемся на событие "Добавление заказа"
 */
AddEventHandler('sale', 'OnOrderAdd', 'sendOrderToAmo');

/**
 * Хэш хука для заказов
 */
const ORDERS_HOOK = 'bc2f7ff007';

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
function sendOrderToAmo($ID, $arFields)
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

    sendToRocket(ORDERS_HOOK, $data);

}

/**
 * Отправка даных на B2B (HTTP POST)
 * @param $hash - хэш хука
 * @param $data - данные для отправки
 */
function sendToRocket($hash, $data)
{

    $url = 'https://b2b.rocketcrm.bz/api/channels/site/form?hash=' . $hash;
    $ch = curl_init($url);

    $encodedData = json_encode($data);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $result = curl_exec($ch);

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