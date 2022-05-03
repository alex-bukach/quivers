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
  - simple_oauth (https://www.drupal.org/project/simple_oauth)
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

# SETUP WITH DOCKER

* Docker Installation

    Install Docker on Ubuntu -> https://docs.docker.com/install/linux/docker-ce/ubuntu/

    Install Docker on CentOS -> https://docs.docker.com/install/linux/docker-ce/centos/

    Install Docker on MacOS -> https://docs.docker.com/docker-for-mac/install/

    Install Docker on Windows -> https://docs.docker.com/docker-for-windows/install/

* Project setup 

    1. git clone <repository-url>

    2. cd quivers.plugin.drupal/
    
    3. sudo docker-compose build && sudo docker-compose up -d

    4. Install drupal using the installation gui using the url - http://localhost:8080

    Values like Database Name, Database User, Database Password and Database Host are defined in the "ENV" file. You can also update the values of the variables before building the container (step 2). You will need these values while installing drupal. 

    5. id=$(sudo docker ps -aqf "name=quiversplugindrupal_drupal_1")

    6. sudo docker exec -it $id drupal-modules

    

