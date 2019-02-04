INTRODUCTION
------------
This module has Custom Drupal Order Workflows with Quivers-Tax Plugin.

REQUIREMENTS
------------
This module requires the following:
* Submodules of Drupal Commerce package (https://drupal.org/project/commerce)
  - Commerce core
  - Commerce Order
  - Commerce Store
  - Commerce Tax

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
  in Quivers Settings.
  Go to Administration > Commerce > Configuration > Quivers Settings
* Add a new Tax type -> Quivers Tax.
  Administration > Commerce > Configuration > Tax Types > Add new tax type.
  Name it and Select 'Quivers Tax' in 'Plugin' options.
* Go to Commerce > Configuration > Checkout flows > Shipping > Edit.
  Make sure Transaction mode for 'Payment process' is set to
  'Authorize only'.
