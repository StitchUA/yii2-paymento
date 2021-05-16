<?php



/* @var $this \yii\web\View */
/* @var $paywall \stitchua\paymento\models\Paywall */

use exts\helpers\Html;

$this->registerJs('$("#paywall-form").submit();');
//echo $paywall->generateSignature(); die();
$form = Html::beginForm($paywall->apiUri, 'post', ['id' => 'paywall-form', 'csrf' => false]);
foreach ($paywall->getPaymentData() as $attrName => $value) {
    if($attrName == 'customer'){
        foreach ($value as $customerAttrName => $cAttrValue) {
            $form .= Html::hiddenInput('customer['.$customerAttrName.']', $cAttrValue);
        }
    } else {
        $form .= Html::hiddenInput($attrName, $value);
    }

}
$form .= Html::hiddenInput('signature', $paywall->generateSignature());
$form .= Html::endForm();
echo $form;
