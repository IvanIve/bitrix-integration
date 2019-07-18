<?

use Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem,
    Bitrix\Sale\Delivery\Services\Manager;

/**
 * Подписываемся на событие сохранения заказа
 */
\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleOrderSaved',
    'processOrder'
);

/**
 * Хэш хука для заказов
 */
const ORDERS_HOOK = 'xxxxxxxxx';
/**
 * Хэш хука для форм
 * (на случай, если он другой)
 */
const FORMS_HOOK = ORDERS_HOOK;

function processOrder ($event)
{
    /** @var Order $order */
    $order = $event->getParameter("ENTITY");
    $oldValues = $event->getParameter("VALUES");
    $isNew = $event->getParameter("IS_NEW");

    if ($isNew) {

        $collection = $order->getPropertyCollection();
        $data = [
            order_id => $order->getId(),
            name => getAttribute($collection, 'IS_PAYER'),
            email => getAttribute($collection, 'IS_EMAIL'),
            phone => getAttribute($collection, 'IS_PHONE'),
            index => getAttribute($collection, 'IS_ZIP'),
            city => getAttribute($collection, 'CODE', 'CITY'),
            address => getAttribute($collection, 'IS_ADDRESS'),
            comment => $order->getField('USER_DESCRIPTION'),
            total => $order->getPrice(),
            currency => $order->getCurrency(),
            basket => "",
        ];

        $basketItems = $order->getBasket()->getOrderableItems();
        $basketItemsCount = count($basketItems);

        foreach ($basketItems as $index => $item)
        {

            $data["basket"] .= $item->getField('NAME') . "\n";
            $data["basket"] .= formatNumber($item->getField('PRICE'));
            $data["basket"] .= " x " . formatNumber($item->getField('QUANTITY'), 0) . $item->getField('MEASURE_NAME') . ' = ';
            $data["basket"] .= formatNumber($item->getField("PRICE") * $item->getField("QUANTITY")) . " " . $item->getField("CURRENCY");

            /**
             * Делаем доп. переносы после позиции
             * если она не последняя
             */
            if ($index + 1 !== $basketItemsCount) {
                $data["basket"] .= "\n\n";
            }
        }

        /**
         * Дополняем данными о доставке
         */
        $delivery = $order->getShipmentCollection()[0];
        $deliveryService = Manager::getObjectById($delivery->getField('DELIVERY_ID'));

        if ($delivery) {
            $data["delivery"] = [
                id => $delivery->getId(),
                name => $delivery->getField("DELIVERY_NAME"),
                description => $deliveryService->getDescription(),
                price => formatNumber($delivery->getField("PRICE_DELIVERY")),
                currency => $delivery->getField("CURRENCY")
            ];
        }

        $payment = $order->getPaymentCollection()[0];

        if ($payment) {
            $data["payment"] = [
                id => $payment->getId(),
                name => $payment->getField("PAY_SYSTEM_NAME")
            ];
        }


        /**
         * Отправка
         */
        send(ORDERS_HOOK, $data);

    }
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

function getAttribute($collection, $name, $value = 'Y')
{
    foreach ($collection as $item)
    {
        $property = $item->getProperty();
        if ($property[$name] === $value)
        {
            return $item->getValue();
        }
    }

    return null;
}
