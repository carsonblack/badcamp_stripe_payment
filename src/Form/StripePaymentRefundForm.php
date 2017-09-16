<?php

namespace Drupal\badcamp_stripe_payment\Form;

use Drupal\badcamp_stripe_payment\Entity\StripePayment;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\stripe_api\StripeApiService;

/**
 * Class StripePaymentRefundForm.
 */
class StripePaymentRefundForm extends FormBase {

  /**
   * Drupal\stripe_api\StripeApiService definition.
   *
   * @var \Drupal\stripe_api\StripeApiService
   */
  protected $stripeApi;

  /**
   * Class constructor.
   */
  public function __construct(StripeApiService $stripeApiService) {
    $this->stripeApi = $stripeApiService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('stripe_api.stripe_api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stripe_payment_refund';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, StripePayment $stripe_payment = NULL) {
    if (!is_null($stripe_payment) && $stripe_payment->get('refunded')->first()->getValue()['value'] != 0){
      drupal_set_message(t('Payment has already been refunded'), 'error');
      $redirect_response = $this->redirect('user.page');
      $redirect_response->send();
    }

    if(!is_null($stripe_payment->get('entity_id')->first())) {
      $node = $stripe_payment->get('entity_id')
        ->first()
        ->get('entity')
        ->getTarget()
        ->getValue();
      $form['message'] = [
        '#markup' => t('Are you sure you want to refund the payment for @title?', [
          '@title' => $node->label()
        ]),
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
    }

    $form['payment'] = [
      '#type' => 'value',
      '#value' => $stripe_payment
    ];

    $form['confirm'] = [
      '#type' => 'submit',
      '#value' => t('Refund My Payment')
    ];

    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('op')->getUntranslatedString() == $form_state->getValue('confirm')->getUntranslatedString()) {

      $payment = $form_state->getValue('payment');

      $amount = $payment->get('amount')->first()->getValue()['value'];
      $charge_id = $payment->get('stripe_transaction_id')->first()->getValue()['value'];
      $refund_params = [
        'amount'  => $amount,
        'charge'  => $charge_id,
      ];

      try {
        $refund = $this->stripeApi->callWithErrors('Refund', 'create', $refund_params);
        $payment->set('refunded', 1);
        $payment->save();
      }
      catch(\Exception $e){
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function refundPaymentAccess(AccountInterface $account, StripePayment $stripe_payment = NULL) {
    $payment_owner = $stripe_payment->get('user_id')->first()->getValue()['target_id'];
    return AccessResult::allowedIf(($account->hasPermission('administer stripe payment entities') || $account->id() == $payment_owner) && $stripe_payment->get('refunded')->first()->getValue()['value'] != 1);
  }
}
