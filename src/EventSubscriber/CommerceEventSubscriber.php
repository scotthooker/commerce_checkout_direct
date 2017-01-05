<?php

namespace Drupal\commerce_checkout_direct\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class CommerceEventSubscriber.
 *
 * @package Drupal\commerce_checkout_direct\EventSubscriber
 */
class CommerceEventSubscriber implements EventSubscriberInterface {

  /**
   * Sets quantity to 1, and sends to checkout process.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   */
  public function onProductAdded(CartEntityAddEvent $event) {
    $cart = $event->getCart();
    // Only act if we are on a checkout direct order type.
    if ($cart->bundle() == 'checkout_direct') {
      // We only want 1 quantity.
      $item_added_sku = $event->getEntity()->getSku();
      $cart_items = $cart->getItems();
      foreach ($cart_items as $cart_item) {
        $item_sku = $cart_item->getPurchasedEntity()->getSku();
        if ($item_sku != $item_added_sku) {
          $cart->removeItem($cart_item);
        }
      }

      $quantity = $cart_items[0]->getQuantity();
      if ($quantity > 1) {
        $cart_items[0]->setQuantity(1);
        $cart->save();
      }
      \Drupal::requestStack()
        ->getCurrentRequest()->attributes->set('commerce_checkout_direct_redirect_url', Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $event->getCart()->id(),
      ])->toString());
    }
  }

  /**
   * Checks if a redirect rules action was executed.
   *
   * Redirects to the provided url if there is one.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The response event.
   */
  public function checkRedirectIssued(FilterResponseEvent $event) {
    $request = $event->getRequest();
    $redirect_url = $request->attributes->get('commerce_checkout_direct_redirect_url');
    if (isset($redirect_url)) {
      $event->setResponse(new RedirectResponse($redirect_url));
    }
  }

  /**
   *
   */
  public static function getSubscribedEvents() {
    $events = [
      CartEvents::CART_ENTITY_ADD => ['onProductAdded', 1000],
      KernelEvents::RESPONSE => ['checkRedirectIssued', -10],
    ];
    return $events;
  }

}