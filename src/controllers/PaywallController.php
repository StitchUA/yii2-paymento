<?php

namespace stitchua\paymento\controllers;

use app\business\SettlePayment;
use app\models\Account;
use app\models\Invoice;
use stitchua\paymento\models\PaymentoTransaction;
use stitchua\paymento\models\Paywall;
use yii\helpers\Json;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Kontroler do obsługi płatności z Paymento
 */
class PaywallController extends Controller
{

    public function beforeAction($action)
    {
        if($action->id == 'ipn'){
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * @param int $id ID faktury
     * @param string|null $payMethod Metoda płatnosci. Paywall::METHOD_CARD lub Paywall::METHOD_PBL.
     * Jeśli nie przekazywać dany parametr, to Paymento wyświetli wszystkie dostępne dla sklepu.
     *
     * @param int|null $accountId ID uzytkownika który opłaca fakturę
     * @return string
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionInvoicePay($id, $payMethod = null, $accountId = null)
    {
        $invoice = Invoice::findOne($id);
        if(!$invoice){
            throw new NotFoundHttpException('Nieznany numer faktury');
        }

        if (is_null($accountId)) {
            $account = $invoice->account;
        } else {
            $account = Account::findOne($accountId);
        }

        if (is_null($account)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $existingPaywall = Paywall::findOne(['orderId' => $invoice->fld_id]);
        if($existingPaywall){
            \Yii::warning([
                'Usuwamy poprzednio wygenerowaną płatność',
                'Attributes' => $existingPaywall->attributes
            ], 'paymento');
            $existingPaywall->delete();
        }

        $paywall = new Paywall( $this->module, [
            'successReturnUrl' => \Yii::$app->urlManager->createAbsoluteUrl(['/site/paymentlandingpage', 'result' => 'success'], 'https'),
            'failureReturnUrl' => \Yii::$app->urlManager->createAbsoluteUrl(['/site/paymentlandingpage', 'result' => 'error'], 'https')
        ]);

        $amount = SettlePayment::calculateAmountForPayment($invoice);
        $paywall->setData($invoice, $account, $amount);
        if($payMethod){
            if($payMethod === Paywall::METHOD_CARD){
                $paywall->payByCard();
            }
            if($payMethod === Paywall::METHOD_PBL){
                $paywall->payByBank();
            }
        }
        if(!$paywall->save()){
            \Yii::error([
                'Nie udało się zapisać dane przekazywane do paymento',
                'Errors' => $paywall->errors,
                'Attributes' => $paywall->attributes
            ], 'paymento');
            \Yii::$app->session->setFlash('error', 'Wystąpił nieoczekiwany błąd. Skątaktuj się z administracją');
            $this->goBack();
        }
        return $this->render('pay', ['paywall' => $paywall]);
    }

    /**
     * Akcja dla notifikacji o statusie tranzakcji z Paymento
     */
    public function actionIpn()
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $transactionData = \Yii::$app->request->post('transaction');
        if(!$transactionData) {
            \Yii::$app->response->setStatusCode(400, Response::$httpStatuses[400]);
            echo Json::encode(['status' => 'error']);
            \Yii::$app->end();
        }
        $transaction = new PaymentoTransaction($this->module);
        $transaction->setAttributes($transactionData, false);
        $transaction->setHeaders(\Yii::$app->request->headers);
        if(!$transaction->validateSignature($this->module)){
            // Błąd weryfikacji sygnatury
            \Yii::$app->response->setStatusCode(400, Response::$httpStatuses[400]);
            echo Json::encode(['status' => 'error']);
            \Yii::$app->end();
        }

        if (!$transaction->save()) {
            \Yii::error([
                'MSG' => 'Nie udało się zapisać notifikację z Paymento',
                '$_POST' => \Yii::$app->request->post(),
                '$transaction->errors' => $transaction->errors,
                '$transaction->attributes' => $transaction->attributes
            ], 'paymento');
        }
        if($transaction->isSettled){
            /** @var Invoice $invoice */
            $invoice = Invoice::findOne($transaction->orderId);
            $invoice->externalPaymentSettle($transaction);
        }

        // Wysyłamy odpowiedź do paymento
        echo Json::encode(['status' => 'ok']);
        \Yii::$app->end();
    }
}
