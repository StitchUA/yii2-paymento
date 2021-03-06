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
            'identyfikator zam??wienia przypisany przez system Paymento'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'type',
            ' w zale??no??ci od transakcji, warto???? sale wyst??puje przy sprzeda??y, refun dla zwrotu'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'status',
            'dla p??atno??ci zaakceptowanej status brzmi settled, dla odrzuconej rejected'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'source',
            'dla tego parametru warto??ci?? mo??e by?? `api` lub `web`'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'serviceId',
            'unikalny identyfikator sklepu w kt??rym zosta??a wykonana transakcja'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'amount',
            'warto???? zam??wienia podana w najmniejszej jednostce pieni????nej waluty'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'title',
            'tytu?? p??atno??ci (przys??any ze sklepu)'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'orderId',
            'numer zam??wienia (przys??any ze sklepu)'
        );

        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'headerMerchantid',
            'identyfikator klienta w Paymento" z nag????wku'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'headerServiceid',
            'identyfikator sklepu w Paymento z nag????wku'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'headerSignature',
            'podpis notyfikacji z nag????wku'
        );
        $this->addCommentOnColumn('{{%paymento_transaction}}',
            'headerAlg',
            'algorytm funkcji skr??tu (mo??liwe warto??ci: sha256) z nag????wku'
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
