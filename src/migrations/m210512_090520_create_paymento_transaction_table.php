<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%paymento_transaction}}`.
 */
class m210512_090520_create_paymento_transaction_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $this->createTable('{{%paymento_paywall}}', [
            'fld_id' => $this->primaryKey()->unsigned(),
            'orderId' => $this->integer()->notNull(),
            'amount' => $this->integer()->notNull(),
            'currency' => $this->string(),
            'customerFirstName' => $this->string(),
            'customerLastName' => $this->string(),
            'customerEmail' => $this->string(),
            'customerCid' => $this->string(),
            'customerIsPep' => $this->string(),
            'customerPhone' => $this->string(),
            'customerCompany' => $this->string(),
            'title' => $this->string(),
            'activeTo' => $this->string(),
            'returnUrl' => $this->string(),
            'failureReturnUrl' => $this->string(),
            'successReturnUrl' => $this->string(),
            'visibleMethod' => $this->string(),
            'signature' => $this->string(),

            'created_at' => $this->dateTime(),
            'updated_at' => $this->dateTime(),
        ]);

        $this->createTable('{{%paymento_transaction}}', [
            'fld_id' => $this->primaryKey()->unsigned(),
            'id' => $this->string()->notNull(),
            'type' => $this->string()->notNull(),
            'status' => $this->string()->notNull(),
            'source' => $this->string()->notNull(),
            'created' => $this->integer(),
            'modified' => $this->integer(),
            'notificationUrl' => $this->string(),
            'serviceId' => $this->string()->notNull(),
            'amount' => $this->integer()->notNull(),
            'currency' => $this->string(),
            'title' => $this->string()->notNull(),
            'orderId' => $this->integer()->notNull(),
            'paymentMethod' => $this->string(),
            'paymentMethodCode' => $this->string(),

            'headerMerchantid' => $this->string(),
            'headerServiceid' => $this->string(),
            'headerSignature' => $this->string(),
            'headerAlg' => $this->string(),

            'created_at' => $this->dateTime()
        ]);

        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'id',
            'identyfikator zamówienia przypisany przez system Paymento'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'type',
            ' w zależności od transakcji, wartość sale występuje przy sprzedaży, refun dla zwrotu'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'status',
            'dla płatności zaakceptowanej status brzmi settled, dla odrzuconej rejected'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'source',
            'dla tego parametru wartością może być `api` lub `web`'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'serviceId',
            'unikalny identyfikator sklepu w którym została wykonana transakcja'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'amount',
            'wartość zamówienia podana w najmniejszej jednostce pieniężnej waluty'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'title',
            'tytuł płatności (przysłany ze sklepu)'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'orderId',
            'numer zamówienia (przysłany ze sklepu)'
        );

        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'headerMerchantid',
            'identyfikator klienta w Paymento" z nagłówku'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'headerServiceid',
            'identyfikator sklepu w Paymento z nagłówku'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'headerSignature',
            'podpis notyfikacji z nagłówku'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'headerAlg',
            'algorytm funkcji skrótu (możliwe wartości: sha256) z nagłówku'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%paymento_transaction}}');
        $this->dropTable('{{%paymento_paywall}}');
    }
}
