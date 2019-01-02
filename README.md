# Quivers Drupal Plugin for Drupal 8.x + Commerce 2.x

This Module uses Quivers Validate API and Quivers Countries API for Tax Calculation.
It also has Stripe Integration with Authorization, Custom Drupal Order Workflows.

## Pre-Installation

Stripe needs stripe-php to run. Install it with Composer.

```bash
composer require stripe/stripe-php:4.3.0
``` 

## Post-Installation

After installing the module, 

Step 1: Go to `Commerce > Configuration > Order Types > Edit > Workflow`. You will see a list, you must pick 'Quivers Integration'. 

Step 2: Go to `Commerce > Configuration > Payment Gateways > Add Payment Gateway`. Enter Stripe Credentials with the Name: 'Stripe Gateway' and Machine Name: 'stripe_gateway' (in order to change the order state directly to 'Ready for Fulfilment' for orders with Stripe Payments).

Step 3: (Optional) Add Commerce Paypal Module. and add a new payment gateway (see Step 2), with Name: 'Paypal Gateway' and Machine Name: 'paypal_gateway' (in order to change the order state directly to 'Ready for Fulfilment' for orders with Paypal Payments).
You can get the module from https://www.drupal.org/project/commerce_paypal for Drupal 8.x.

Note: If Order State Doesn't change or an error is shown after placing an order, for Stripe and Paypal Orders, then apply this patch from the forum: https://www.drupal.org/project/drupal/issues/2974156