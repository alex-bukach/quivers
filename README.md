INTRODUCTION
------------
This module has Custom Drupal Order Workflows with Quivers-Tax Plugin.

REQUIREMENTS
------------
This module requires the following:
* Drupal Core Module Dependencies:
  - options
  - rest
  - taxonomy
  - user
* Drupal Contrib Module Dependencies:
  - profile
  - state_machine
  - commerce
  * Submodules of Drupal Commerce package (https://drupal.org/project/commerce)
    - commerce_order
    - commerce_product
    - commerce_store
    - commerce_tax
    - commerce_payment
* Drupal third-party Module Dependencies:
  - commerce_stripe (https://www.drupal.org/project/stripe)
  - commerce_shipping (https://www.drupal.org/project/commerce_shipping)
  - oauth (https://www.drupal.org/project/oauth)
  - tracking_number (https://www.drupal.org/project/tracking_number)


INSTALLATION
------------
* This module can be installed with any one of the following extensions
zip, tar, tgz, gz or bz2 of the repo.


CONFIGURATION
-------------
* Select an Order-Type:
  Go to Commerce > Configuration > Order Types > Edit > Workflow
  'Quivers Order Fulfilment'
* Update IDs Provided by your project manager at Quivers 
  in Quivers Settings and Quivers Tax Settings.
  1) Go to Administration > Commerce > Configuration > Quivers Settings
  2) Go to Administration > Commerce > Configuration > Quivers Tax Settings
* Add a new Tax type -> Quivers Tax.
  Administration > Commerce > Configuration > Tax Types > Add new tax type.
  Name it and Select 'Quivers Tax' in 'Plugin' options.
* Go to Commerce > Configuration > Checkout flows > Shipping > Edit.
  Make sure Transaction mode for 'Payment process' is set to
  'Authorize only'.
